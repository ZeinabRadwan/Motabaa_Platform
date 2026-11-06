<?php

namespace App\Support\Hr;

/**
 * Cycle detection for self-referencing trees (department parents, employee managers).
 */
final class HrHierarchy
{
    private const MAX_DEPTH = 200;

    /**
     * True when making $newParentId the parent of $nodeId would create a loop.
     *
     * @param  callable(int): ?int  $parentOf  returns the current parent id of a node, or null at the root
     */
    public static function createsCycle(int $nodeId, ?int $newParentId, callable $parentOf): bool
    {
        $cursor = $newParentId;
        $visited = [];
        for ($depth = 0; $cursor !== null && $depth < self::MAX_DEPTH; $depth++) {
            if ($cursor === $nodeId || isset($visited[$cursor])) {
                return true;
            }
            $visited[$cursor] = true;
            $next = $parentOf($cursor);
            $cursor = $next === null ? null : (int) $next;
        }

        return $cursor !== null;
    }
}
