<?php

defined('CONTROLADORIA') || exit;

function ideas_context(): array
{
    $month = month_key();
    return [
        'top' => array_merge(top_videos($month, 8), top_videos(shift_month($month, -1), 8)),
        'terms' => search_terms($month, 15),
        'rising' => rising_terms($month, 8),
        'existing' => array_column(db_all('SELECT title FROM ideas ORDER BY created_at DESC LIMIT 40'), 'title'),
    ];
}

function valid_idea_draft($item): bool
{
    return is_array($item)
        && is_string($item['titulo'] ?? null) && mb_strlen($item['titulo']) >= 5 && mb_strlen($item['titulo']) <= 140
        && is_string($item['porque'] ?? null) && mb_strlen($item['porque']) >= 10 && mb_strlen($item['porque']) <= 400
        && in_array($item['fonte'] ?? null, ['buscas', 'comentarios', 'desempenho'], true)
        && is_numeric($item['pontuacao'] ?? null);
}

function ask_gemini_for_ideas(array $gemini, array $context): array
{
    $lines = static fn (array $rows, callable $format) => implode("\n", array_map($format, $rows)) ?: '- nenhum';
    $prompt = "Você ajuda a equipe de um canal brasileiro do YouTube a decidir as próximas pautas.\n"
        . "Com base nos dados abaixo, sugira 5 ideias de vídeo novas, específicas e diferentes das já cadastradas.\n"
        . 'Responda apenas com um array JSON de objetos: {"titulo": string, "porque": string (1 frase citando o dado que motivou), '
        . '"fonte": "buscas" | "desempenho" | "comentarios", "pontuacao": inteiro de 0 a 100 indicando o potencial}.' . "\n\n"
        . "Vídeos com mais views recentemente (título, views, retenção média):\n"
        . $lines($context['top'], static fn ($v) => "- {$v['title']} | {$v['views']} | " . round($v['avg_view_pct']) . '%') . "\n\n"
        . "Termos de busca do YouTube que trouxeram gente ao canal neste mês (termo, views):\n"
        . $lines($context['terms'], static fn ($t) => "- {$t['term']} | {$t['views']}") . "\n\n"
        . "Termos que mais cresceram vs mês anterior (média por dia):\n"
        . $lines($context['rising'], static fn ($t) => "- {$t['term']} | {$t['views']} views" . ($t['growth'] !== null ? ' | ' . round($t['growth'] * 100) . '%' : ' | novo')) . "\n\n"
        . "Ideias já cadastradas (não repetir):\n"
        . $lines($context['existing'], static fn ($title) => "- $title");

    $response = http_request(
        'POST',
        'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($gemini['model']) . ':generateContent',
        ['Content-Type: application/json', 'x-goog-api-key: ' . $gemini['api_key']],
        json_encode([
            'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
            'generationConfig' => ['responseMimeType' => 'application/json', 'temperature' => 0.8],
        ], JSON_UNESCAPED_UNICODE),
        45
    );
    if ($response['status'] === 0) {
        throw new UserFacingException('Não foi possível conectar ao Gemini. Tente de novo em instantes.');
    }
    if ($response['status'] !== 200) {
        throw new UserFacingException('O Gemini recusou o pedido (erro ' . $response['status'] . '). Confira a chave e o modelo em Integrações.');
    }

    $text = json_decode($response['body'], true)['candidates'][0]['content']['parts'][0]['text'] ?? '';
    $items = json_decode((string) $text, true);
    if (is_array($items) && isset($items['titulo'])) {
        $items = [$items];
    } elseif (is_array($items) && !isset($items[0])) {
        // Às vezes o modelo embrulha a lista num objeto: {"ideias": [...]}
        foreach ($items as $value) {
            if (is_array($value) && isset($value[0])) {
                $items = $value;
                break;
            }
        }
    }
    if (!is_array($items)) {
        throw new UserFacingException('O Gemini devolveu uma resposta num formato inesperado. Tente de novo.');
    }
    return array_values(array_filter($items, 'valid_idea_draft'));
}

/** Sem chave do Gemini: cria sugestões simples a partir dos termos de busca que estão crescendo. */
function example_ideas(array $context): array
{
    $existing = mb_strtolower(implode(' ', $context['existing']));
    $templates = [
        '%s: o guia que eu queria ter lido antes',
        'Testei %s por 30 dias e isso aconteceu',
        'Os 5 erros mais comuns sobre %s',
        '%s na prática: meu passo a passo',
    ];
    $drafts = [];
    foreach ($context['rising'] as $term) {
        if (count($drafts) >= 4 || ($term['growth'] !== null && $term['growth'] <= 0) || str_contains($existing, mb_strtolower($term['term']))) {
            continue;
        }
        $index = count($drafts);
        $label = mb_strtoupper(mb_substr($term['term'], 0, 1)) . mb_substr($term['term'], 1);
        $drafts[] = [
            'titulo' => sprintf($templates[$index % count($templates)], $index === 1 ? $term['term'] : $label),
            'porque' => $term['growth'] === null
                ? '"' . $term['term'] . '" apareceu pela primeira vez nas buscas e já trouxe ' . fmt_number($term['views']) . ' views este mês.'
                : 'Buscas por "' . $term['term'] . '" cresceram ' . round($term['growth'] * 100) . '% (média por dia) e trouxeram ' . fmt_number($term['views']) . ' views este mês.',
            'fonte' => 'buscas',
            'pontuacao' => max(40, 90 - $index * 10),
        ];
    }
    return $drafts;
}

function generate_ideas(): array
{
    $context = ideas_context();
    $gemini = gemini_config();
    $drafts = $gemini !== null ? ask_gemini_for_ideas($gemini, $context) : example_ideas($context);
    $generatedBy = $gemini !== null ? 'ia' : 'exemplo';
    $now = to_db(utc_now());
    foreach ($drafts as $draft) {
        db_insert('ideas', [
            'title' => mb_substr($draft['titulo'], 0, 200),
            'rationale' => $draft['porque'],
            'source' => $draft['fonte'],
            'score' => max(0, min(100, (int) $draft['pontuacao'])),
            'status' => 'nova',
            'generated_by' => $generatedBy,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
    return ['created' => count($drafts), 'generated_by' => $generatedBy];
}
