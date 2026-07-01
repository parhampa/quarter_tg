<?php
namespace Core;

class RequestHandler
{
    private $commandMap;

    public function __construct(array $commandMap = [])
    {
        $this->commandMap = $commandMap;
    }

    public function setCommandMap(array $commandMap): void
    {
        $this->commandMap = $commandMap;
    }

    public function parseCommand(array $update): ?array
    {
        $message = $update['message'] ?? null;
        if (!$message || !isset($message['text'])) {
            return null;
        }

        $text = trim($message['text']);
        $command = null;
        $args = [];

        if (strpos($text, '/') === 0) {
            $parts = explode(' ', $text);
            $cmd = substr($parts[0], 1);
            if (strpos($cmd, '@') !== false) {
                $cmd = explode('@', $cmd)[0];
            }
            $command = $cmd;
            $args = array_slice($parts, 1);
        } else {
            $matchedKey = null;
            $commandKeys = array_keys($this->commandMap);
            usort($commandKeys, function($a, $b) {
                return strlen($b) - strlen($a);
            });
            foreach ($commandKeys as $key) {
                if (strpos($text, $key) === 0) {
                    $matchedKey = $key;
                    break;
                }
            }
            if ($matchedKey !== null) {
                $command = $matchedKey;
                $rest = substr($text, strlen($matchedKey));
                $rest = trim($rest);
                if ($rest !== '') {
                    $args = explode(' ', $rest);
                } else {
                    $args = [];
                }
            }
        }

        return $command !== null ? ['command' => $command, 'args' => $args] : null;
    }
}