<?php

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
     * @param array $attrs The tag attributes as a name => value lookup.
     * @param string $text The inner tag HTML.
     * @param int $options The tag options (one of {@see Form::SELF_CLOSING}).
     * @return string
     */
    static function element($tag, $attrs, $text, $options = null)
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
     * @param bool $checked Whether the checkbox should be checked.
     * @param array $attrs The tag attributes as a name => value lookup.
     * @param int $options The tag options (one of {@see Form::SELF_CLOSING}).
     * @return string
     */
    static function checkbox($name, $checked, $attrs = [], $options = null)
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
     * @param int $selectedIndex The index of the option to select.
     * @param array $selectOptions The options with which to populate the dropdown as a key => value lookup.
     * @param array $attrs The tag attributes as a name => value lookup.
     * @param int $options The tag options (one of {@see Form::SELF_CLOSING}).
     * @return string
     */
    static function select($name, $selectedIndex, $selectOptions, $attrs = [], $options = null)
    {
        $attrs['name'] = $name;
        $attrs['id'] = $name;

        /* generate <option> tags */
        $optionTags = '';
        foreach ($selectOptions as $index => $value) {
            $optionAttrs = Array('value' => $index);
            if ($index == $selectedIndex)
                $optionAttrs['selected'] = 'selected';
            $optionTags .= self::element('option', $optionAttrs, $value);
        }

        /* generate <select> */
        return self::element('select', $attrs, $optionTags, $options);
    }
}
