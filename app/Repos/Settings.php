<?php

declare(strict_types=1);

namespace Angelma\Repos;

final class Settings extends Repo
{
    protected const TABLE = 'settings';

    /** @return array<string, string> */
    public function all(): array
    {
        $out = [];
        foreach ($this->db->all('SELECT key, value FROM settings') as $row) {
            $out[(string) $row['key']] = (string) $row['value'];
        }
        return $out;
    }

    public function get(string $key, string $default = ''): string
    {
        $value = $this->db->value('SELECT value FROM settings WHERE key = ?', [$key]);
        return $value === null ? $default : (string) $value;
    }

    public function set(string $key, string $value): void
    {
        $this->db->upsertSetting($key, $value);
    }

    /** @param array<string, string|int> $values */
    public function setMany(array $values): void
    {
        $this->db->transaction(function () use ($values): void {
            foreach ($values as $key => $value) {
                $this->set($key, (string) $value);
            }
        });
    }
}
