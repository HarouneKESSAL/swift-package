<?php

declare(strict_types=1);

namespace Swift\Security\IAM;

final class PolicyEngine
{
    /**
     * Simple matcher: supports * wildcards in actions/resources
     *
     * @param PolicyDocument[] $documents
     */
    public function isAllowed(array $documents, string $action, string $resourceArn): bool
    {
        // DENY overrides ALLOW
        foreach ($documents as $doc) {
            foreach ($doc->statements as $st) {
                if ($st->effect === PolicyStatement::EFFECT_DENY
                    && $this->matches($action, $st->actions)
                    && $this->matches($resourceArn, $st->resources)) {
                    return false;
                }
            }
        }

        foreach ($documents as $doc) {
            foreach ($doc->statements as $st) {
                if ($st->effect === PolicyStatement::EFFECT_ALLOW
                    && $this->matches($action, $st->actions)
                    && $this->matches($resourceArn, $st->resources)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @param string[] $patterns */
    private function matches(string $value, array $patterns): bool
    {
        foreach ($patterns as $p) {
            $regex = '/^' . str_replace(['*', '/'], ['.*', '\/'], preg_quote($p, '/')) . '$/';
            if (preg_match($regex, $value)) {
                return true;
            }
        }
        return false;
    }
}