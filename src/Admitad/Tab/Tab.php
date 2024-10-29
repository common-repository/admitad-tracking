<?php

namespace Admitad\Tab;

class Tab
{
    protected string $name = '';

    public function init(): void
    {
        register_setting(
            $this->getPageName(),
            $this->getSettingName(),
            [
                'sanitize_callback' => [$this, 'handle'],
                'default' => $this->getDefaults(),
            ]
        );

        add_settings_section(
            $this->getSettingName(),
            __('Main settings', 'admitadtracking'),
            [$this, 'renderSection'],
            $this->getPageName()
        );

        foreach ($this->getSettings() as $id => $row) {
            $row['id'] = $row['label_for'] = $id;
            $label = $row['label'] ?? '';
            $callback = $row['callback'] ?? [$this, 'display_settings'];

            add_settings_field(
                $id,
                $label,
                $callback,
                $this->getPageName(),
                $this->getSettingName(),
                $row
            );
        }
    }

    public function handle($options): ?array
    {
        if (isset($_POST['reset'])) {
            update_option('admitadtracking_auth', [
                'campaign_code' => '',
                'postback_key' => '',
            ]);
        }

        return $options;
    }

    public function renderSection(): void {}

    public function render(): void
    {
        settings_fields($this->getPageName());
        do_settings_sections($this->getPageName());
        submit_button();
    }

    public function getPageName(): string
    {
        return 'admitadtracking-' . $this->name . '-page';
    }

    public function getSettingName(): string
    {
        return 'admitadtracking_' . $this->name;
    }

    public function display_settings($args): void
    {
        extract($args);

        $option_name = $this->getSettingName();

        $o = get_option($option_name);

        switch ($type) {
            case 'text':
                $value = !empty($o[$id]) ? esc_attr(stripslashes($o[$id])) : ($default ?? null);
                $name ??= $option_name . '[' . $id . ']';
                echo "<input class='' type='text' id='{$id}' placeholder='" . $label . "' name='" . $name . "' value='{$value}' />";
                echo (isset($args['desc'])) ? '<br /><span class="description">' . $args['desc'] . '</span>' : '';

                break;
            case 'textarea':
                $o[$id] = esc_attr(stripslashes($o[$id]));
                echo "<textarea class='code large-text' cols='30' rows='10' type='text' id='{$id}' name='" . $option_name . "[{$id}]'>{$o[$id]}</textarea>";
                echo (isset($args['desc'])) ? '<br /><span class="description">' . $args['desc'] . '</span>' : '';

                break;
            case 'checkbox':
                $checked = ('on' == $o[$id]) ? " checked='checked'" : '';
                echo "<label><input type='checkbox' id='{$id}' name='" . $option_name . "[{$id}]' {$checked} /> ";
                echo $args['desc'] ?? '';
                echo '</label>';

                break;
            case 'checkbox-group':
                echo '<ul style="margin-top: 10px;">';

                foreach ($vals as $v => $l) {
                    echo '<li>';
                    $checked = (isset($o[$id]) && $o[$id] == $v) ? " checked='checked'" : '';
                    echo "<label><input type='checkbox' id='{$id}' name='" . $option_name . "[{$id}]' value='{$v}' {$checked} /> ";
                    echo ('' != $l) ? $l : '';
                    echo '</label>';
                    echo '</li>';
                }
                echo '<ul>';

                break;
            case 'select':
                echo "<select id='{$id}' name='" . ($name ?? $option_name . "[{$id}]") . "'>";
                $value ??= $o[$id];

                foreach ($vals as $v => $l) {
                    $selected = ($value == $v) ? "selected='selected'" : '';
                    echo "<option value='{$v}' {$selected}>{$l}</option>";
                }
                echo '</select>';
                echo (isset($args['desc'])) ? '<br /><span class="description">' . $args['desc'] . '</span>' : '';

                break;
            case 'multiselect':
                echo "<select style='width: 90%; line-height: 0;' id='{$id}' name='" . (isset($name) ? $name . '[]' : $option_name . "[{$id}][]") . "' multiple='multiple'>";
                $value ??= $o[$id];

                foreach ($vals as $v => $l) {
                    $selected = (in_array($v, $value)) ? "selected='selected'" : '';
                    echo "<option value='{$v}' {$selected}>{$l}</option>";
                }
                echo '</select>';
                echo (isset($args['desc'])) ? '<br /><span class="description">' . $args['desc'] . '</span>' : '';

                break;
            case 'radio':
                echo '<fieldset>';

                foreach ($vals as $v => $l) {
                    $checked = ($o[$id] == $v) ? "checked='checked'" : '';
                    echo "<label><input type='radio' name='" . $option_name . "[{$id}]' value='{$v}' {$checked} />{$l}</label><br />";
                }
                echo '</fieldset>';

                break;
            case 'info':
                echo '<p>' . $text . '</p>';

                break;
        }
    }

    protected function getDefaults(): array
    {
        return [];
    }

    protected function getSettings(): array
    {
        return [];
    }
}
