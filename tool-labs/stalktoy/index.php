<?php
declare(strict_types=1);

require_once('../backend/modules/Backend.php');
require_once('../backend/modules/IPAddress.php');
require_once('../backend/modules/Form.php');
$backend = Backend::create('Stalk toy', 'View global details about a user across all Wikimedia wikis. You can provide an account name (like <a href="/stalktoy/Pathoschild?defer=1" title="view result for Pathoschild" data-undefer><tt>Pathoschild</tt></a>), an IPv4 address (like <a href="/stalktoy/127.0.0.1?defer=1" title="view result for 127.0.0.1" data-undefer><tt>127.0.0.1</tt></a>), an IPv6 address (like <a href="/stalktoy/2001:db8:1234::?defer=1" title="view result for 2001:db8:1234::" data-undefer><tt>2001:db8:1234::</tt></a>), or a CIDR block (like <a href="/stalktoy/212.75.0.1/16?defer=1" title="view result for 212.75.0.1/16" data-undefer><tt>212.75.0.1/16</tt></a> or <a href="/stalktoy/2600:3C00::/48?defer=1" title="view result for 2600:3C00::/48" data-undefer><tt>2600:3C00::/48</tt></a>).')
    ->link('/stalktoy/stylesheet.css')
    ->link('/content/jquery.tablesorter.js')
    ->link('https://tools-static.wmflabs.org/cdnjs/ajax/libs/echarts/6.1.0/echarts.common.min.js')
    ->link('/stalktoy/scripts.js')
    ->link('/content/submitRoute.js')
    ->link('/content/undefer.js')
    ->header();

spl_autoload_register(function ($className) {
    // strip namespace
    $parts = explode('\\', $className);
    $className = array_pop($parts);

    // load file
    foreach (["framework/$className.php", "framework/models/$className.php"] as $path) {
        if (file_exists($path))
            include($path);
    }
});

#############################
## Instantiate script engine
#############################
$backend->profiler->start('initialize');
$targetForm = '';

# parse target
# stalktoy is an edge case for route values: an IP range like '127.0.0.1/16' should be treated as one value despite the path separator.
$target = $backend->getString('target', allowBlank: false);
if (!$target) {
    $target = $backend->getRouteValue();
    if ($target != null && $backend->getRouteValue(1) != null)
        $target .= '/' . $backend->getRouteValue(1);
}

# initialise
$deferRun = !empty($target) && $backend->isDeferRequested();
$engine = new StalktoyEngine($backend, $deferRun ? null : $target);
$engine->showAllWikis = $backend->getBool('show_all_wikis') ?? false;
$engine->showDetached = $backend->getBool('show_detached') ?? false;
$engine->showGroupsPerWiki = $backend->getBool('global_groups_per_wiki') ?? false;
$deletedGlobalGroups = ['Cabal'];

$backend->profiler->stop('initialize');

#############################
## Input form
#############################
$targetForm = '';
if ($deferRun)
    $targetForm = $backend->formatValue($backend->formatUsername($target));
else if ($engine->isValid())
    $targetForm = $backend->formatValue($engine->target);

echo "
    <p>Who shall we stalk?</p>
    <form action='/stalktoy' method='get' data-submit-route='/stalktoy/{*target}'>
        <div>
            <input type='text' name='target' value='$targetForm' />
            <input type='submit' value='Analyze »' /> <br />

            ", Form::checkbox('show_all_wikis', $engine->showAllWikis), "
            <label for='show_all_wikis'>Show wikis with no matching account or block.</label><br />
            ", Form::checkbox('show_detached', $engine->showDetached), "
            <label for='show_detached'>Show detached local accounts (slower).</label><br />
            ", Form::checkbox('global_groups_per_wiki', $engine->showGroupsPerWiki), "
            <label for='global_groups_per_wiki'>Show relevant global groups for each wiki.</label><br />
        </div>
    </form>
    ";

#############################
## Deferred lookup
#############################
if ($deferRun) {
    echo "
        <div class='result-box'>
            {$backend->getDeferredHtml("Analyze »")}
        </div>
    ";
}

#############################
## Process data (IP / CIDR)
#############################
$ip = $engine->getGlobalIp($engine->target);
if ($engine->isValid() && $ip->ip->isValid()) {
    ########
    ## Fetch data
    ########
    /* global data */
    $backend->profiler->start('fetch global');
    $global = [
        'wikis' => $engine->wikis,
        'ip' => $ip,
        'pretty_range' => $ip->ip->getFriendly(IPAddress::START) . ' &mdash; ' . $ip->ip->getFriendly(IPAddress::END)
    ];
    $backend->profiler->stop('fetch global');

    /* local data */
    $backend->profiler->start('fetch local');
    $localBlocks = $engine->getLocalIpBlocksByWiki($ip, $global['wikis']);
    $backend->profiler->stop('fetch local');


    ########
    ## Output
    ########
    $backend->profiler->start('output');
    echo $engine->renderQueryErrors();
    echo "
        <div class='result-box'>
            <h3>", ($ip->ip->isIPv4() ? 'IPv4' : 'IPv6'), " ", ($ip->ip->isRange() ? ' range' : ' address'), "</h3>
        ", ($ip->ip->isRange() ? "<b>{$global['pretty_range']}</b><br />" : "");

    ####
    ## Global blocks
    ####
    echo '<h4>Global blocks</h4>';
    if ($global['ip']->globalBlocks) {
        echo '
            <ul>
        ';
        foreach ($global['ip']->globalBlocks as $block) {
            $byUrl = urlencode($block->by);
            $reason = $engine->formatReason($block->reason, 'meta.wikimedia.org');
            echo "<li>{$block->timestamp} &mdash; {$block->expiry}: <b>{$block->target}</b> globally blocked by <a href=\"https://meta.wikimedia.org/wiki/user:$byUrl\">{$block->by}</a> (<small>$reason</small>)</li>";
        }
        echo '
            </ul>
        ';
    }
    else
        echo '<em>No global blocks.</em><br />';

    ####
    ## Local blocks
    ####
    /* get wikis to show */
    $blockedWikis = array_filter($localBlocks);
    $shownWikis = $engine->showAllWikis
        ? $global['wikis']
        : array_intersect_key($global['wikis'], $blockedWikis);

    echo '<h4>Local blocks</h4>';
    if (!$shownWikis)
        echo '<em>No local blocks.</em><br />';
    else {
        /* print header */
        echo '
            <table class="pretty sortable" id="local-ips">
                <thead>
                    <tr>
                    <th>wiki</th>
                    <th>blocked</th>
                    </tr>
                </thead>
                <tbody>
            ';

        /* print each row */
        foreach ($shownWikis as $wiki => $wikiData) {
            $domain = $wikiData->domain;
            $blocked = (int)(bool)$localBlocks[$wiki];
            $open = (int)!$wikiData->isClosed;
            $linkWiki = $engine->link($domain, 'user:' . $engine->targetWikiUrl, $domain);

            echo "
                <tr data-open='$open' data-blocked='{$blocked}'>
                    <td class='wiki'>{$linkWiki}</td>
                    <td class='blocks'>
                ";
            if ($localBlocks[$wiki]) {
                foreach ($localBlocks[$wiki] as $block) {
                    $reason = $engine->formatReason($block->reason, $domain);
                    echo "<span class='is-block-start'>{$block->timestamp}</span> &mdash; <span class='is-block-end'>{$block->expiry}</span>: <b>{$engine->formatValue($block->target)}</b> blocked by <span class='is-block-admin'>{$engine->formatValue($block->by)}</span> (<span class='is-block-reason'>{$engine->formatValue($reason)}</span>)<br />";
                }
            }
            echo "
                    </td>
                </tr>
                ";
        }

        /* print footer */
        echo '
                </tbody>
            </table>
        ';
    }
    if (!$engine->showAllWikis)
        echo "<small>Only wikis with a local block are shown. You can enable 'Show wikis with no matching account or block' to list every wiki.</small>";

    ####
    ## Steward tools
    ####
    echo "
        <h4>See also</h4>
        <div>
            Related toys:
            <a href='https://whois.toolforge.org/w/", $ip->ip->getFriendly(), "' title='whois query'>whois</a>,
            <a href='https://bullseye.toolforge.org/ip/", $ip->ip->getFriendly(), "' title='bullseye tool'>bullseye</a>,
            <a href='https://meta.wikimedia.org/wiki/Special:GlobalBlock?wpAddress={$engine->targetWikiUrl}' title='Special:GlobalBlock'>global block</a>.
        </div>
    ";

    ####
    ## Footer
    ####
    echo '</div>';
    $backend->profiler->stop('output');
}

#############################
## Process data (user)
#############################
else if ($engine->isValid() && $engine->target) {
    #######
    ## Fetch data
    ########
    /* global details */
    $backend->profiler->start('fetch global account');
    $account = $engine->getGlobal($engine->target);
    if ($account->exists) {
        $stats = [
            'wikis' => 0,
            'edit_count' => 0,
            'most_edits' => -1,
            'most_edits_domain' => null,
            'oldest' => null,
            'oldest_raw' => 999999999999999,
            'oldest_domain' => null,
            'unified_wikis' => 0,
            'detached_wikis' => 0
        ];
    } else {
        $stats = [
            'most_edits' => -1,
            'most_edits_domain' => null
        ];
    }
    $backend->profiler->stop('fetch global account');

    /* global groups */
    $globalGroupsByWiki = [];
    if ($account->exists && $account->groups) {
        $backend->profiler->start('fetch global groups by wiki');
        $globalGroupsByWiki = $engine->getGlobalGroupsByWiki($account->id, $account->wikis);
        $backend->profiler->stop('fetch global groups by wiki');
    }

    /* local details */
    $backend->profiler->start('fetch local accounts');
    $local = [];

    $searchAllWikis = $engine->showDetached || $engine->showAllWikis;
    $searchWikis = $searchAllWikis
        ? $engine->wikis
        : array_intersect_key($engine->wikis, $account->wikiHash);

    $localAccounts = $searchWikis
        ? $engine->getLocalAccounts($engine->target, $searchWikis, $account->wikiHash)
        : [];

    foreach ($searchWikis as $wiki => $wikiData) {
        $domain = $wikiData->domain;
        $localAccount = $localAccounts[$wiki] ?? $engine->getMissingLocalAccount($wikiData);

        if ($localAccount->exists || $engine->showAllWikis)
            $local[$wiki] = $localAccount;

        if ($localAccount->exists) {
            /* statistics used even when no global account */
            if ($localAccount->editCount > $stats['most_edits']) {
                $stats['most_edits'] = $localAccount->editCount;
                $stats['most_edits_domain'] = $domain;
            }

            /* statistics shown only for global account */
            if ($account->exists && $localAccount->exists) {
                $stats['wikis']++;
                if ($engine->getWikiUnifiable($wiki)) {
                    if ($localAccount->isUnified)
                        $stats['unified_wikis']++;
                    else
                        $stats['detached_wikis']++;
                }
                $stats['edit_count'] += $localAccount->editCount;
                if ($localAccount->registeredRaw && $localAccount->registeredRaw < $stats['oldest_raw']) {
                    $stats['oldest'] = $localAccount->registered;
                    $stats['oldest_raw'] = $localAccount->registeredRaw;
                    $stats['oldest_domain'] = $domain;
                }
            }
        }
    }
    $backend->profiler->stop('fetch local accounts');

    /* best guess for pre-2005 oldest account */
    if ($account->exists && !$stats['oldest']) {
        if (array_key_exists($account->homeWiki, $local) && !$local[$account->homeWiki]->registeredRaw) {
            $homeWiki = $local[$account->homeWiki];
            $stats['oldest'] = $homeWiki->registered;
            $stats['oldest_raw'] = $homeWiki->registeredRaw;
            $stats['oldest_domain'] = $homeWiki->wiki->domain;
        }
    }


    #######
    ## Output global details
    ########
    $backend->profiler->start('output');
    echo $engine->renderQueryErrors();
    echo "
        <div class='result-box'>
            <h3>Global account</h3>\n
        ";

    echo "
        <div class='is-global-details' data-is-global='", ($account->exists ? '1' : '0'), "'";
    if ($account->exists) {
        echo "
            data-home-wiki=\"{$backend->formatValue($account->homeWiki)}\"
            data-status=\"", ($account->isLocked ? 'locked' : 'okay'), "\"
            data-id=\"{$account->id}\"
            data-registered=\"{$account->registered}\"
            data-groups=\"{$backend->formatValue(implode(', ', $account->groups))}\"
        ";
    }
    echo '>';
    if ($account->exists) {
        $globalGroups = '&mdash;';
        if ($account->groups) {
            $globalGroups = [];
            foreach ($account->groups as $group) {
                $globalGroups[] = in_array($group, $deletedGlobalGroups)
                    ? $backend->formatValue($group)
                    : "<a href='{$backend->getToolUrl('globalgroups')}#{$backend->formatAnchor($group)}' title='View global group details'>{$backend->formatValue(str_replace('_', ' ', $group))}</a>";
            }
            $globalGroups = implode(', ', $globalGroups);
        }

        echo "<table class='plain'>";
        if ($stats['detached_wikis'] > 0) {
            echo "
                <tr>
                    <td>SUL:</td>
                    <td class='error'><strong>Warning:</strong> This global account has ", $stats['detached_wikis'], " detached account", ($stats['detached_wikis'] > 1 ? 's' : ''), ". If you own this name, you should <a href='https://meta.wikimedia.org/wiki/Help:Unified_login'>claim ownership of detached accounts</a>.</div></td>
                </tr>
                ";
        }
        echo "
            <tr>
                <td>Home:</td>
            ";
        if ($account->homeWiki)
            echo "<td><a href='https://{$engine->wikis[$account->homeWiki]->domain}/wiki/user:{$engine->targetWikiUrl}' title='home wiki'>{$engine->wikis[$account->homeWiki]->domain}</a></td>";
        else
            echo "<td><b>unknown</b> <small>(it might be <a href='https://meta.wikimedia.org/wiki/Oversight' title='about hiding user names'>hidden</a> or renamed, or the data might not be replicated yet)</small></td>";
        echo "
            </tr>
            <tr>
                <td>Status:</td>
                <td>";
        if ($account->isLocked)
            echo "<span class='bad'>Locked</span> ";
        else if ($engine->target == 'Shanel')
            echo "<span class='good'>&nbsp;&hearts;&nbsp;</span>";
        else
            echo "<span class='good'>okay</span>";
        echo "
            </tr>
            <tr>
                <td>Registered:</td>
                <td>{$account->registered} <span class='account-id'>(account #{$account->id})</span></td>
            </tr>
            <tr>
                <td>Groups:</td>
                <td>$globalGroups</td>
            </tr>
            <tr>
                <td style='vertical-align:top;'>Statistics:</td>
                <td>
                    {$stats['edit_count']} edits on {$stats['wikis']} wikis.<br />
                    Most edits on <a href='https://{$stats['most_edits_domain']}/wiki/Special:Contributions/{$engine->targetWikiUrl}'>{$stats['most_edits_domain']}</a> ({$stats['most_edits']}).<br />
        ";
        if ($stats['oldest']) {
            echo "Oldest account on <a href='https://{$stats['oldest_domain']}/wiki/user:{$engine->targetWikiUrl}'>{$stats['oldest_domain']}</a> (", ($stats['oldest'] ? $stats['oldest'] : '2005 or earlier, so probably inaccurate; registration date was not stored until late 2005'), ").";
        }

        if ($stats['edit_count'] > 0) {
            echo "
                    <div id='account-charts'></div>
            ";
        }

        echo "
                    </td>
                </tr>
            </table>
            See also
            <a href='{$backend->getToolUrl('crossactivity', $engine->target)}?defer=1' title='recent activity' data-undefer>recent activity</a>,
            <a href='{$backend->getToolUrl('userpages', $engine->target)}?defer=1' title='user pages' data-undefer>user pages</a>,
            <a href='https://meta.wikimedia.org/wiki/Special:CentralAuth/{$engine->targetWikiUrl}' title='Special:CentralAuth'>global user manager</a>.
            ";
    } else
        echo '<div class="neutral">There is no global account with this name, or it has been <a href="https://meta.wikimedia.org/wiki/Oversight" title="about hiding user names">globally hidden</a>.</div>';
    echo '</div>';


    ########
    ## Output local wikis
    ########
    echo "<h3>Local accounts</h3>\n";

    if (count($local)) {
        /* precompile */
        $unifiedLabels = ['local', 'unified', 'n/a'];

        /* output */
        echo "
            <table class='pretty sortable' id='local-accounts'>
                <thead>
                    <tr>
                        <th>wiki</th>
                        <th>edits</th>
                        <th>registered</th>
                        <th>groups</th>
                        ", ($engine->showGroupsPerWiki && $globalGroupsByWiki ? '<th>global groups</th>' : ''), "
                        <th><a href='https://meta.wikimedia.org/wiki/Help:Unified_login' title='about unified login'>unified login</a></th>
                        <th>block</th>
                    </tr>
                </thead>
            <tbody>
            ";

        foreach ($local as $dbname => $user) {
            ########
            ## Prepare strings
            ########
            $wiki = $user->wiki;
            $linkWiki = $engine->link($wiki->domain, "User:{$engine->targetWikiUrl}", $wiki->domain);

            /* user exists */
            if ($user->exists) {
                $linkEdits = $engine->link($wiki->domain, "Special:Contributions/{$engine->targetWikiUrl}", $user->editCount);
                $hasGroups = (int)(bool)$user->groups;
                $isBlocked = (int)$user->isBlocked;
                $isHidden = (int)($isBlocked && $user->block->isHidden);
                $isUnified = (int)$user->isUnified;
                $isUnifiable = (int)$engine->getWikiUnifiable($dbname);
                $labelUnified = $unifiedLabels[$isUnified];
                if (!$isUnified && !$isUnifiable)
                    $labelUnified = $unifiedLabels[2];

                $hasGlobalGroups = $globalGroupsByWiki && isset($globalGroupsByWiki[$dbname]) && $globalGroupsByWiki[$dbname];
                $globalGroups = $hasGlobalGroups
                    ? implode(', ', $globalGroupsByWiki[$dbname])
                    : '&nbsp;';

                if ($user->isBlocked) {
                    $reason = $engine->formatReason($user->block->reason, $wiki->domain);
                    $blockSummary = "<span class='is-block-start'>{$user->block->timestamp}</span> &mdash; <span class='is-block-end'>{$user->block->expiry}</span>: blocked by <span class='is-block-admin'>{$engine->formatText($user->block->by)}</span> (<span class='is-block-reason'>{$engine->formatText($reason)}</span>)";
                } else
                    $blockSummary = '&nbsp;';
            } /* user doesn't exist */
            else {
                $linkEdits = '&nbsp;';
                $hasGroups = 0;
                $isBlocked = 0;
                $isHidden = 0;
                $isUnified = 0;
                $labelUnified = 'no such user';
                $blockSummary = '&nbsp;';
                $globalGroups = '&nbsp;';
                $hasGlobalGroups = '';
                $isUnifiable = 0;
            }

            ########
            ## Output
            ########
            $family = $wiki->family;
            if ($wiki->name == 'sourceswiki')
                $family = 'wikisource';
            echo "
                <tr
                    data-wiki='{$wiki->name}'
                    data-domain='{$wiki->domain}'
                    data-lang='", ($wiki->isMultilingual ? 'multilingual' : $wiki->lang), "'
                    data-family='$family'
                    data-open='", (int)!$wiki->isClosed, "'
                    data-exists='", (int)(bool)$user->exists, "'
                    data-edits='{$user->editCount}'
                    data-groups='", (int)$hasGroups, "'
                    data-global-groups='", (int)($hasGlobalGroups), "'
                    data-registered='{$user->registered}'
                    data-unified='", (int)$user->isUnified, "'
                    data-unifiable='", (int)$isUnifiable, "'
                    data-blocked='", (int)$user->isBlocked, "'
                >
                    <td class='wiki'>$linkWiki</td>
                    <td class='edit-count'>$linkEdits</td>
                    <td class='timestamp'>{$user->registered}</td>
                    <td class='groups'>{$engine->formatText($user->groups)}</td>
                    ", ($engine->showGroupsPerWiki && $globalGroupsByWiki ? "<td class='global-groups'>{$engine->formatText($globalGroups)}</td>" : ''), "
                    <td class='unification'>$labelUnified</td>
                    <td class='blocks'>$blockSummary</td>
                </tr>
                ";
        }
        echo '</tbody></table>';

        /* filtered note */
        if (!$searchAllWikis)
            echo "<small>Only wikis linked to the global account are shown. You can enable 'Show detached local accounts' to search for those (slower).</small>";

        echo '</div>';
    }
    else if (!$searchAllWikis) {
        echo
            "<div class='neutral'>",
            ($account->exists
                ? "No local accounts were found, but only the wikis attached to the global account were checked."
                : "No local accounts were searched, since there's no global account."
            ),
            " You can enable 'Show detached local accounts' to search for those (slower).</div>";
    }
    else
        echo "<div class='error'>There are no local accounts with this name.</div>\n";
    $backend->profiler->stop('output');
}

$backend->footer();
