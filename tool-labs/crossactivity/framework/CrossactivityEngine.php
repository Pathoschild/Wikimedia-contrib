<?php
declare(strict_types=1);

/**
 * The tool engine.
 */
class CrossactivityEngine extends Base
{
    ##########
    ## Public methods
    ##########
    /**
     * Get HTML for a table cell containing a formatted date colored-coded by age.
     * @param string|null $date The date to format.
     */
    function getColoredCellHtml(?string $date): string
    {
        if (!$date)
            $color = "CCC";
        else {
            $dateValue = substr(str_replace('-', '', $date), 0, 8);
            if ($dateValue >= date('Ymd', strtotime('-1 week')))
                $color = "CFC";
            else if ($dateValue >= date('Ymd', strtotime('-3 week')))
                $color = "FFC";
            else
                $color = "FCC";
        }

        return "<td style='background-color:#$color;'>$date</td>";
    }

    /**
     * Get HTML for a table cell containing a list of groups.
     * @param string|null $groups The comma-separated groups to show.
     */
    function getGroupCellHtml(?string $groups): string
    {
        $text = $this->formatText($groups);

        return empty($groups)
            ? "<td style='background-color:#CCC;'>&nbsp;</td>"
            : "<td>$text</td>";
    }

    /**
     * Get an HTML wiki link.
     * @param string $domain The wiki domain.
     * @param string $page The page title.
     */
    function getLinkHtml(string $domain, string $page): string
    {
        return "<a href='https://{$domain}?title=" . urlencode($page) . "' title='" . htmlspecialchars($page) . "'>$domain</a>";
    }
}
