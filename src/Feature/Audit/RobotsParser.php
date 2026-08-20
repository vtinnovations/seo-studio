<?php

declare(strict_types=1);

/*
 * AI SEO Studio
 *
 * Package: vtinnovations/seo-studio
 * Copyright: VT Innovations Team
 * Licence: LGPL-3.0-or-later
 */

namespace VTinnovations\SeoStudio\Feature\Audit;

/**
 * Minimal, standards-conformant robots.txt evaluation (RFC 9309 semantics):
 * groups by User-agent, longest-path-match wins, Allow wins ties. We only
 * need the verdict for "/" (site-wide access), so no wildcard expansion
 * beyond prefix matching is required.
 */
final class RobotsParser
{
    /** @var array<string, list<array{allow: bool, path: string}>> lowercased agent token => rules */
    private array $groups = [];

    /** @var list<string> */
    private array $sitemaps = [];

    public static function parse(string $content): self
    {
        $self = new self();
        $currentAgents = [];
        $rulesSeen = false;

        foreach (preg_split('/\r\n|\r|\n/', $content) ?: [] as $line) {
            $line = trim((string) preg_replace('/#.*$/', '', $line));
            if ($line === '' || !str_contains($line, ':')) {
                continue;
            }

            [$field, $value] = array_map('trim', explode(':', $line, 2));
            $field = strtolower($field);

            if ($field === 'user-agent') {
                // A new user-agent line after rules starts a NEW group.
                if ($rulesSeen) {
                    $currentAgents = [];
                    $rulesSeen = false;
                }
                $agent = strtolower($value);
                $currentAgents[] = $agent;
                $self->groups[$agent] ??= [];
                continue;
            }

            if ($field === 'sitemap') {
                if ($value !== '') {
                    $self->sitemaps[] = $value;
                }
                continue;
            }

            if ($field === 'allow' || $field === 'disallow') {
                $rulesSeen = true;
                foreach ($currentAgents as $agent) {
                    $self->groups[$agent][] = [
                        'allow' => $field === 'allow',
                        'path' => $value,
                    ];
                }
            }
        }

        return $self;
    }

    /**
     * Verdict for the given agent on path "/": true = may crawl.
     */
    public function isAllowed(string $agentToken, string $path = '/'): bool
    {
        $rules = $this->rulesFor($agentToken);

        $bestLength = -1;
        $bestAllow = true; // no matching rule => allowed

        foreach ($rules as $rule) {
            $rulePath = $rule['path'];

            if ($rulePath === '') {
                // "Disallow:" (empty) means allow everything — matches with length 0.
                $matches = true;
                $length = 0;
                $effectiveAllow = true;
            } else {
                $matches = $this->pathMatches($rulePath, $path);
                $length = \strlen($rulePath);
                $effectiveAllow = $rule['allow'];
            }

            if (!$matches) {
                continue;
            }

            if ($length > $bestLength || ($length === $bestLength && $effectiveAllow && !$bestAllow)) {
                $bestLength = $length;
                $bestAllow = $effectiveAllow;
            }
        }

        return $bestAllow;
    }

    /** Whether the agent is addressed by an EXPLICIT group (not just "*"). */
    public function hasExplicitGroup(string $agentToken): bool
    {
        return isset($this->groups[strtolower($agentToken)]);
    }

    /**
     * @return list<string>
     */
    public function getSitemaps(): array
    {
        return $this->sitemaps;
    }

    /**
     * @return list<array{allow: bool, path: string}>
     */
    private function rulesFor(string $agentToken): array
    {
        $token = strtolower($agentToken);

        // Exact/substring product-token match first (RFC: longest match on token).
        if (isset($this->groups[$token])) {
            return $this->groups[$token];
        }

        foreach ($this->groups as $agent => $rules) {
            if ($agent !== '*' && (str_contains($token, $agent) || str_contains($agent, $token))) {
                return $rules;
            }
        }

        return $this->groups['*'] ?? [];
    }

    private function pathMatches(string $rulePath, string $path): bool
    {
        // Support "*" wildcards and "$" end anchor (Google extension, de-facto standard).
        if (str_contains($rulePath, '*') || str_ends_with($rulePath, '$')) {
            $pattern = str_replace('\*', '.*', preg_quote(rtrim($rulePath, '$'), '~'));
            $anchor = str_ends_with($rulePath, '$') ? '$' : '';

            return (bool) preg_match('~^' . $pattern . $anchor . '~', $path);
        }

        return str_starts_with($path, $rulePath);
    }
}
