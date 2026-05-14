<?php

namespace App\Importers\Email;

/**
 * Extracts structured lead fields from a raw email body.
 *
 * Handles two layouts:
 *   1. Labeled fields — "Name: John Doe\nPhone: +44…\nMessage: …"
 *      Common output from Contact Form 7, Gravity Forms, Typeform, etc.
 *   2. Unstructured prose — the whole body becomes the lead message.
 *
 * HTML bodies are converted to plain text before parsing.
 */
final class MailBodyParser
{
    private const NAME_LABELS = [
        'name', 'full name', 'full_name', 'your name', 'first name',
        'contact name', 'sender',
    ];

    private const PHONE_LABELS = [
        'phone', 'tel', 'telephone', 'mobile', 'cell', 'phone number',
        'mob', 'phone no', 'contact number',
    ];

    private const MESSAGE_LABELS = [
        'message', 'comments', 'comment', 'enquiry', 'inquiry',
        'description', 'details', 'note', 'notes', 'body', 'your message',
    ];

    /**
     * @return array{name: string|null, phone: string|null, message: string|null}
     */
    public function parse(string $rawBody, bool $isHtml = false): array
    {
        $text = $isHtml ? $this->htmlToText($rawBody) : $rawBody;
        $text = $this->normalizeWhitespace($text);

        $lines = array_values(array_filter(
            explode("\n", $text),
            static fn (string $l) => trim($l) !== ''
        ));

        $result       = ['name' => null, 'phone' => null, 'message' => null];
        $messageLines = [];
        $collectingMessage = false;

        foreach ($lines as $line) {
            if ($collectingMessage) {
                $messageLines[] = $line;
                continue;
            }

            // "Label: value" — label up to 40 chars, colon, optional value on same line
            if (preg_match('/^([^:]{1,40})\s*:\s*(.*)$/u', $line, $m)) {
                $label = mb_strtolower(trim($m[1]));
                $value = trim($m[2]);
                $field = $this->resolveField($label);

                if ($field === 'name' && $result['name'] === null) {
                    $result['name'] = $value !== '' ? $value : null;
                } elseif ($field === 'phone' && $result['phone'] === null) {
                    $result['phone'] = $value !== '' ? $value : null;
                } elseif ($field === 'message' && $result['message'] === null) {
                    if ($value !== '') {
                        $result['message'] = $value;
                    } else {
                        $collectingMessage = true;
                    }
                }
            }
        }

        if ($collectingMessage && $messageLines !== []) {
            $result['message'] = implode("\n", $messageLines);
        }

        // No structured fields at all → treat entire body as message
        if ($result['name'] === null && $result['phone'] === null && $result['message'] === null) {
            $result['message'] = $text !== '' ? $text : null;
        }

        return $result;
    }

    private function resolveField(string $label): ?string
    {
        if (in_array($label, self::NAME_LABELS, true)) {
            return 'name';
        }
        if (in_array($label, self::PHONE_LABELS, true)) {
            return 'phone';
        }
        if (in_array($label, self::MESSAGE_LABELS, true)) {
            return 'message';
        }

        return null;
    }

    private function htmlToText(string $html): string
    {
        // Insert newlines before block-level closing/opening tags so field
        // labels survive the strip_tags pass.
        $html = preg_replace('/<\/(p|div|tr|li|h[1-6]|br)>/i', "\n", $html) ?? $html;
        $html = preg_replace('/<(br|p|div|tr|li|h[1-6])\b[^>]*>/i', "\n", $html) ?? $html;
        $text = strip_tags($html);

        return html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function normalizeWhitespace(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;

        return trim($text);
    }
}
