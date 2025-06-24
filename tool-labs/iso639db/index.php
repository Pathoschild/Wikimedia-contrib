<?php
declare(strict_types=1);

require_once('../backend/modules/Backend.php');
require_once('../backend/modules/Database.php');
require_once('framework/Iso639dbEngine.php');
$backend = Backend::create('ISO 639 database', 'A searchable database of languages and ISO 639 codes augmented by native language names from Wikipedia.')
    ->link('/iso639db/stylesheet.css')
    ->link('/content/jquery.collapse/jquery.collapse.js')
    ->link('/content/jquery.multiselect/jquery.multiselect.js')
    ->link('/content/jquery.multiselect/jquery.multiselect.css')
    ->link('scripts.js')
    ->header();

####################
## Output form
####################
$engine = new Iso639dbEngine($backend);

echo "
    <div class='search'>
        <h2>Which languages would you like to see?</h2>
    
        <form action='{$backend->url('/iso639db')}' method='get'>
            <label for='code'>By ISO 639 code:</label>
            <input type='text' id='code' name='code' value='{$backend->formatValue($engine->code)}' style='width:3em;' disabled />
    
            <label for='name'>or language name:</label>
            <input type='text' id='name' name='name' value='{$backend->formatValue($engine->name)}' disabled />
    
            <select id='filters' name='filters[]' multiple='multiple' disabled><!--Only show languages...-->
                <optgroup label='in:'>",
                    $engine->getFilterOptionHtml('1', 'ISO 639-1'),
                    $engine->getFilterOptionHtml('2', 'ISO 639-2'),
                    $engine->getFilterOptionHtml('2/B', 'ISO 639-2/B'),
                    $engine->getFilterOptionHtml('3', 'ISO 639-3'), "
                </optgroup>
                <optgroup label='of scope:'>
                    <!--<a href='https://iso639-3.sil.org/about/scope' title='about scopes'>scope</a>-->",
                    $engine->getFilterOptionHtml('individual'),
                    $engine->getFilterOptionHtml('dialect'),
                    $engine->getFilterOptionHtml('macrolanguage'),
                    $engine->getFilterOptionHtml('collection'),
                    $engine->getFilterOptionHtml('reserved'),
                    $engine->getFilterOptionHtml('special'), "
                </optgroup>
                <optgroup label='of type:'>",
                    $engine->getFilterOptionHtml('living'),
                    $engine->getFilterOptionHtml('extinct'),
                    $engine->getFilterOptionHtml('ancient'),
                    $engine->getFilterOptionHtml('historic'),
                    $engine->getFilterOptionHtml('constructed'), "
                </optgroup>
            </select><br/>
    
            <input type='submit' value='search' disabled/>
        </form>
    </div>
    ";

####################
## Disabled
####################
echo "<div class='error'>This tool didn't survive the transition to Wikimedia Toolforge. It's temporarily offline until it's reimplemented. Sorry!</div>";
$backend->footer();
die();

####################
## Search result
####################
$rows = $engine->execute();
if (!count($rows))
    echo "<div class='neutral'>There are no results matching your search.</div>";
else {
    echo "
        <table>
            <tr>
                <th>standard</th>
                <th>code</th>
                <th>name</th>
                <th>type</th>
                <th>scope</th>
                <th>notes</th>
            </tr>
            <tbody>
        ";
    foreach ($rows as $row) {
        echo "
            <tr>
                <td><span style='color:gray;'>ISO 639-</span>{$row['list']}</td>
                <td>{$row['code']}</td>
                <td>{$row['name']}", ($row['native_name'] && $row['name'] != $row['native_name'] ? "; <i>{$row['native_name']}</i>" : ""), "</td>
                <td>{$row['type']}</td>
                <td>{$row['scope']}</td>
                <td>{$row['notes']}</td>
            </tr>
            ";
    }
    echo "
            </tbody>
        </table>
        ";

    if (count($rows) == Iso639dbEngine::MAX_LIMIT)
        echo "<div class='neutral'>Only the first ", Iso639dbEngine::MAX_LIMIT, " matching languages are shown.</div>";
}

$backend->footer();