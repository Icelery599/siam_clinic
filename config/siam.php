<?php
/**
 * SIAM — Smart Intake & Assessment Module
 *
 * This is a transparent, rule-based matcher: it does NOT call an external
 * AI API. It reads what the patient types, breaks it into words and short
 * phrases, and scores each department by how many of its configured
 * keywords/symptoms appear in that text. It's fast, free, needs no API key,
 * and its reasoning is fully inspectable (see admin/siam_log.php).
 *
 * To upgrade this to a real LLM-backed version later: keep siam_assess()'s
 * signature the same, and inside it, call an AI API with the patient's
 * input_text and the department list, asking it to return the best-matching
 * department id (or several ranked). Everything else in the app (booking
 * flow, admin log, appointments table) already supports that swap.
 */

/**
 * Score every department against the patient's free-text symptom description.
 *
 * @param PDO    $pdo
 * @param string $inputText Raw text typed by the patient (e.g. "my tooth hurts and gum is bleeding")
 * @return array{
 *   ranked: array<int, array{department_id:int, name:string, score:int, matched_keywords:string[]}>,
 *   best: ?array,
 *   confidence: string
 * }
 */
function siam_assess(PDO $pdo, string $inputText): array
{
    $normalized = strtolower(trim($inputText));
    // Split into words, stripping punctuation, keeping it simple and dependency-free.
    $words = preg_split('/[^a-z0-9]+/', $normalized, -1, PREG_SPLIT_NO_EMPTY);
    $wordSet = array_unique($words);

    $departments = $pdo->query('SELECT id, name, keywords FROM departments ORDER BY name ASC')->fetchAll();

    $ranked = [];
    foreach ($departments as $dept) {
        $keywords = array_filter(array_map('trim', explode(',', strtolower($dept['keywords'] ?? ''))));
        $matched = [];

        foreach ($keywords as $keyword) {
            if ($keyword === '') continue;
            if (str_contains($keyword, ' ')) {
                // Multi-word phrase (e.g. "chest pain") — check substring match.
                if (str_contains($normalized, $keyword)) {
                    $matched[] = $keyword;
                }
            } else {
                // Single word — check against tokenized word set (avoids partial-word false matches).
                if (in_array($keyword, $wordSet, true)) {
                    $matched[] = $keyword;
                }
            }
        }

        if ($matched) {
            $ranked[] = [
                'department_id'    => (int) $dept['id'],
                'name'             => $dept['name'],
                'score'            => count($matched),
                'matched_keywords' => $matched,
            ];
        }
    }

    usort($ranked, fn($a, $b) => $b['score'] <=> $a['score']);

    $best = $ranked[0] ?? null;
    $confidence = 'low';
    if ($best) {
        if ($best['score'] >= 3) $confidence = 'high';
        elseif ($best['score'] === 2) $confidence = 'medium';
        else $confidence = 'low';
    }

    return [
        'ranked'     => array_slice($ranked, 0, 3), // top 3 suggestions
        'best'       => $best,
        'confidence' => $confidence,
    ];
}

/** Persist a SIAM intake for admin visibility and future tuning of keyword lists. */
function siam_log_assessment(PDO $pdo, ?int $patientId, string $inputText, array $assessment): void
{
    $bestDeptId = $assessment['best']['department_id'] ?? null;
    $matchedKeywords = $assessment['best']['matched_keywords'] ?? [];

    $stmt = $pdo->prepare('
        INSERT INTO siam_assessments (patient_id, input_text, matched_department_id, matched_keywords, confidence)
        VALUES (?, ?, ?, ?, ?)
    ');
    $stmt->execute([
        $patientId,
        $inputText,
        $bestDeptId,
        implode(', ', $matchedKeywords),
        $assessment['confidence'],
    ]);
}
