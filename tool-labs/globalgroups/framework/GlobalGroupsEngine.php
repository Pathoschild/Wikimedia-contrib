<?php

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
     * @param array $a The left group.
     * @param array $b The right group.
     * @return int
     */
    function groupSort($a, $b)
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
