<?php

namespace Admitad\Tab;

use Admitad\AdmitadContainer;
use Admitad\AdmitadParameterStrategy;

class ActionsTab extends Tab
{
    protected string $name = 'actions';

    public function __construct(protected AdmitadContainer $container) {}

    public function handle($options): array
    {
        foreach ($this->getFullTariffMap() as $action) {
            $actionCode = $action['action_code'];

            foreach ($action['tariffs'] as $tariff) {
                $tariffCode = $tariff['tariff_code'];

                if (isset($options['actions'][$actionCode]['tariffs'][$tariffCode])) {
                    continue;
                }

                $options['actions'][$actionCode]['tariffs'][$tariffCode] = ['categories' => []];
            }
        }

        return parent::handle($options);
    }

    public function renderSection(): void
    {
        ?>
        <table class="wp-list-table widefat fixed striped posts">
            <thead>
            <tr>
                <th><?php echo esc_html__('Code', 'admitadtracking'); ?></th>
                <th><?php echo esc_html__('Name', 'admitadtracking'); ?></th>
                <th><?php echo esc_html__('Filter', 'admitadtracking'); ?></th>
            </tr>
            </thead>
            <?php
            foreach ($this->getFullTariffMap() as $action) {
                $this->renderAction($action);
            }
        ?>
        </table>
        <?php
    }

    protected function renderAction($action): void
    {
        $prefix = $this->getSettingName() . '[actions][' . $action['action_code'] . ']';

        ?>
        <tr>
            <td style="padding: 20px 10px;"><b><?php echo $action['action_code']; ?></b></td>
            <td style="padding: 20px 10px;"><b><?php echo $action['action_name']; ?></b></td>
            <td>
                <?php
                $this->display_settings(
                    [
                        'type' => 'select',
                        'label' => 'Multi select',
                        'id' => $prefix . '[type]',
                        'name' => $prefix . '[type]',
                        'vals' => $this->getActionTypes(),
                        'value' => $action['type'],
                    ]
                );
        ?>
                <?php
            $this->display_settings(
                [
                    'type' => 'select',
                    'label' => 'User type',
                    'id' => $prefix . '[user_type]',
                    'name' => $prefix . '[user_type]',
                    'value' => $action['user_type'],
                    'vals' => $this->getUserTypes(),
                ]
            );
        ?>
            </td>
        </tr>
        <?php
        foreach ($action['tariffs'] as $tariff) {
            $this->renderTariff($tariff, $action);
        }
    }

    protected function renderTariff($tariff, $action): void
    {
        $prefix = $this->getSettingName() . '[actions][' . $action['action_code'] . '][tariffs][' . $tariff['tariff_code'] . ']';

        ?>
        <tr>
            <td><?php echo $tariff['tariff_code']; ?></td>
            <td><?php echo $tariff['tariff_name']; ?></td>
            <td>
                <?php
                $this->display_settings(
                    [
                        'type' => 'multiselect',
                        'label' => 'Multi select',
                        'id' => $prefix . '[categories]',
                        'name' => $prefix . '[categories]',
                        'vals' => $this->getCategoryMap(),
                        'value' => $tariff['categories'],
                    ]
                );
        ?>
            </td>
        </tr>
        <?php
    }

    protected function getUserTypes(): array
    {
        return [
            AdmitadParameterStrategy::USER_TYPE_NONE => __('None', 'admitadtracking'),
            AdmitadParameterStrategy::USER_TYPE_NEW => __('New user', 'admitadtracking'),
            AdmitadParameterStrategy::USER_TYPE_OLD => __('Old user', 'admitadtracking'),
        ];
    }

    protected function getActionTypes(): array
    {
        return [
            __('Not active', 'admitadtracking'),
            __('Sale', 'admitadtracking'),
        ];
    }

    protected function getActionDefaultOptions(): array
    {
        return [
            'tariffs' => [],
            'type' => null,
            'price_from' => null,
            'price_to' => null,
            'user_type' => null,
        ];
    }

    protected function getTariffDefaultOptions(): array
    {
        return ['categories' => []];
    }

    protected function getDefaults(): array
    {
        return ['actions' => []];
    }

    protected function getFullTariffMap($optionName = 'actions'): array
    {
        $data = $this->container->getAdmitadManager()->getAdvertiserInfo();
        $optionsMap = $this->container->getSettings()->get($optionName, 'actions') ?: [];
        $map = [];

        foreach ($data['actions'] as $actionData) {
            $actionCode = $actionData['action_code'];

            foreach ($actionData['tariffs'] as $tariffData) {
                $tariffCode = $tariffData['tariff_code'];

                if (isset($optionsMap[$actionCode]['tariffs'][$tariffCode])) {
                    $map[$actionCode]['tariffs'][$tariffCode] = $optionsMap[$actionCode]['tariffs'][$tariffCode];
                }
            }

            if (!isset($optionsMap[$actionCode])) {
                continue;
            }

            foreach ($optionsMap[$actionCode] as $key => $value) {
                if ('tariffs' == $key) {
                    continue;
                }

                $map[$actionCode][$key] = $value;
            }
        }

        foreach ($data['actions'] as $action) {
            $actionCode = $action['action_code'];

            foreach ($this->getActionDefaultOptions() as $key => $value) {
                if (!isset($map[$actionCode][$key])) {
                    $map[$actionCode][$key] = $value;
                }
            }

            foreach ($action['tariffs'] as $tariff) {
                $tariffCode = $tariff['tariff_code'];

                if (!isset($map[$actionCode]['tariffs'][$tariffCode])) {
                    $map[$actionCode]['tariffs'][$tariffCode] = $this->getTariffDefaultOptions();
                }

                $map[$actionCode]['tariffs'][$tariffCode] = array_merge($tariff, $map[$actionCode]['tariffs'][$tariffCode]);
            }

            $map[$actionCode] = array_merge($action, $map[$actionCode]);
        }

        return $map;
    }

    protected function getCategoryMap(): array
    {
        $map = [];

        foreach ($this->getCategories() as $category) {
            $map[$category->term_id] = str_repeat(' . ', $category->depth) . $category->name;
        }

        return $map;
    }

    protected function getCategories(): array
    {
        $categories = [];
        $roots = [];

        foreach (get_terms('product_cat') as $category) {
            $categories[$category->term_id] = $category;

            $category->children = [];

            if (!$category->parent) {
                $roots[] = $category;

                continue;
            }

            if (isset($categories[$category->parent]) and $categories[$category->parent]->children) {
                $categories[$category->parent]->children[] = $category;
            }
        }

        return $this->getWithChildren($roots);
    }

    protected function getWithChildren($categories, $level = 0): array
    {
        $result = [];

        foreach ($categories as $category) {
            $category->depth = $level;
            $result[] = $category;

            foreach ($this->getWithChildren($category->children, $level + 1) as $child) {
                $result[] = $child;
            }
        }

        return $result;
    }
}
