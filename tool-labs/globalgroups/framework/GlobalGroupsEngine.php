<?php
declare(strict_types=1);

/**
 * The tool engine.
 */
class GlobalGroupsEngine extends Base
{
    ##########
    ## Public methods
    ##########
    /**
     * Compare two groups for sorting based on the $sort value.
     * @param array<'members'|'rights'|'name', mixed> $a The left group.
     * @param array<'members'|'rights'|'name', mixed> $b The right group.
     * @param 'members'|'name'|'permissions' $sortBy The field to sort by.
     */
    function groupSort(array $a, array $b, string $sortBy): int
    {
        global $sort;
        switch ($sort) {
            case 'members':
                $countA = $a['members'];
                $countB = $b['members'];
                if ($countA == $countB)
                    return 0;
                if ($countA < $countB)
                    return 1;
                return -1;

            case 'permissions':
                $countA = count($a['rights']);
                $countB = count($b['rights']);
                if ($countA == $countB)
                    return 0;
                if ($countA < $countB)
                    return 1;
                return -1;

            default:
                return strcasecmp($a['name'], $b['name']);
        }
    }
}
