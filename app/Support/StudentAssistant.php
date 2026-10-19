<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Throwable;

/**
 * Answers a student's question about using EduVers: where things are and how the
 * system works. Nothing else.
 *
 * It answers from config/assistant.php first. Those answers cost nothing, work with
 * no internet, and cannot help with a quiz because the assistant holds no subject
 * material at all. Only a question none of them match is passed to the AI service,
 * and only when a key is configured.
 */
class StudentAssistant
{
    /** Words too common to tell topics apart. */
    private const NOISE = ['the', 'and', 'for', 'how', 'what', 'where', 'can', 'you', 'this', 'that', 'with', 'from', 'are', 'was', 'does', 'did', 'see', 'get', 'find', 'open', 'show', 'tell'];

    /**
     * @return array{answer: string, url: ?string, label: ?string, source: string}
     */
    public function answer(string $question): array
    {
        $question = trim($question);

        if ($question === '') {
            return $this->reply('Ask me anything about using EduVers - where to find your grades, your subjects, or how quizzes work.');
        }

        if ($topic = $this->bestTopic($question)) {
            return $this->reply($topic['answer'], $topic['route'] ?? null, $topic['label'] ?? null, 'guide');
        }

        return $this->askAi($question) ?? $this->reply($this->cannotHelp());
    }

    /** The topic sharing the most words with the question, or null when none does. */
    private function bestTopic(string $question): ?array
    {
        $words = $this->words($question);

        if ($words === []) {
            return null;
        }

        $best = null;
        $bestScore = 0;

        foreach (config('assistant.topics', []) as $topic) {
            $score = 0;

            foreach ($topic['keywords'] ?? [] as $keyword) {
                foreach ($words as $word) {
                    // "grades" matches "grade", "quizzes" matches "quiz"
                    if (str_starts_with($word, $keyword) || str_starts_with($keyword, $word)) {
                        $score++;
                        break;
                    }
                }
            }

            if ($score > $bestScore) {
                $best = $topic;
                $bestScore = $score;
            }
        }

        return $best;
    }

    /** @return array<int, string> */
    private function words(string $question): array
    {
        $words = preg_split('/[^a-z0-9]+/', Str::lower($question), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_filter(
            $words,
            fn (string $word) => strlen($word) > 2 && ! in_array($word, self::NOISE, true),
        ));
    }

    /**
     * The free AI service, for questions the guide does not cover. Returns null when
     * no key is set, the service is unreachable, or it strays off the subject - in
     * every one of those cases the student gets the plain "here is what I can do".
     */
    private function askAi(string $question): ?array
    {
        $key = (string) config('assistant.ai.key');

        if ($key === '') {
            return null;
        }

        try {
            $response = Http::timeout((int) config('assistant.ai.timeout', 12))
                ->asJson()
                ->post(sprintf(
                    '%s/%s:generateContent?key=%s',
                    config('assistant.ai.endpoint'),
                    config('assistant.ai.model'),
                    urlencode($key),
                ), [
                    'systemInstruction' => ['parts' => [['text' => $this->rules()]]],
                    'contents'          => [['parts' => [['text' => $question]]]],
                    'generationConfig'  => ['maxOutputTokens' => 300, 'temperature' => 0.2],
                ]);

            if ($response->failed()) {
                return null;
            }

            $text = trim((string) data_get($response->json(), 'candidates.0.content.parts.0.text', ''));
        } catch (Throwable $e) {
            report($e); // offline or the free quota ran out: fall back to the guide

            return null;
        }

        return $text === '' ? null : $this->reply($text, source: 'ai');
    }

    /** What the AI service is allowed to be. */
    private function rules(): string
    {
        return <<<'TEXT'
        You are the help assistant inside EduVers, a school learning system used by students.

        You may only help with using EduVers itself: where a page is, what a feature does,
        how to sign in, where grades and lessons live.

        You must refuse anything else, including: answering quiz or exam questions, solving
        homework, explaining subject material, writing essays or code, and general knowledge.
        For those, reply exactly: "I can only help with using EduVers. For questions about
        your lessons, please ask your teacher."

        Never guess at a feature you are unsure of - say you are not sure and suggest asking
        the teacher or the Registrar's Office.

        Answer in at most three short sentences, in plain English, addressed to a student.
        TEXT;
    }

    private function cannotHelp(): string
    {
        return "I can only help with using EduVers - finding your grades, subjects, lessons, "
            ."quizzes, announcements or your profile. For questions about the lessons themselves, "
            .'please ask your teacher.';
    }

    private function reply(string $answer, ?string $route = null, ?string $label = null, string $source = 'guide'): array
    {
        return [
            'answer' => $answer,
            'url'    => $route && Route::has($route) ? route($route) : null,
            'label'  => $label,
            'source' => $source,
        ];
    }
}
