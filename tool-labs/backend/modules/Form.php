<?php
declare(strict_types=1);

/**
 * Provides static helpers for generating form elements.
 */
class Form
{
    ##########
    ## Properties
    ##########
    /**
     * A bit flag indicating a tag is self-closing.
     */
    const SELF_CLOSING = 1;


    ##########
    ## Public methods
    ##########
    /**
     * Get an HTML string for a generic element.
     * @param string $tag The tag name.
     * @param array<string, mixed> $attrs The tag attributes as a name => value lookup.
     * @param string|null $text The inner tag HTML.
     * @param int|null $options The tag options (one of {@see Form::SELF_CLOSING}).
     */
    static function element(string $tag, array $attrs, ?string $text, ?int $options = null): string
    {
        $out = "<{$tag}";

        foreach ($attrs as $field => $value)
            $out .= " {$field}='{$value}'";

        if ($options & self::SELF_CLOSING)
            $out .= " />";
        else
            $out .= ">{$text}</{$tag}>";

        return $out;
    }

    /**
     * Get an HTML string for a checkbox.
     * @param string $name The checkbox name value.
     * @param bool|null $checked Whether the checkbox should be checked.
     * @param array<string, mixed> $attrs The tag attributes as a name => value lookup.
     * @param int|null $options The tag options (one of {@see Form::SELF_CLOSING}).
     */
    static function checkbox(string $name, ?bool $checked, array $attrs = [], ?int $options = null): string
    {
        $attrs['type'] = 'checkbox';
        $attrs['name'] = $name;
        $attrs['id'] = $name;
        if ($checked)
            $attrs['checked'] = 'checked';

        return self::element('input', $attrs, null, $options | self::SELF_CLOSING);
    }

    /**
     * Get an HTML string for a drop-down menu.
     * @param string $name The dropdown name value.
     * @param bool|int|string|null $selectedKey The key of the option to select.
     * @param array<string, string> $selectOptions The options with which to populate the dropdown as a key => value lookup.
     * @param array<string, mixed> $attrs The tag attributes as a name => value lookup.
     * @param int|null $options The tag options (one of {@see Form::SELF_CLOSING}).
     */
    static function select(string $name, bool|int|string|null $selectedKey, array $selectOptions, array $attrs = [], ?int $options = null): string
    {
        $attrs['name'] = $name;
        $attrs['id'] = $name;

        /* generate <option> tags */
        $optionTags = '';
        foreach ($selectOptions as $key => $value) {
            $optionAttrs = ['value' => $key];
            if ($key == $selectedKey)
                $optionAttrs['selected'] = 'selected';
            $optionTags .= self::element('option', $optionAttrs, $value);
        }

        /* generate <select> */
        return self::element('select', $attrs, $optionTags, $options);
    }
}
