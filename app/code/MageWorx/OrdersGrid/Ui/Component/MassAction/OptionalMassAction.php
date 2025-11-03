<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace MageWorx\OrdersGrid\Ui\Component\MassAction;

use Magento\Ui\Component\Action;

class OptionalMassAction extends Action
{
    /**
     * @inheritDoc
     */
    public function prepare()
    {
        if (!empty($this->actions)) {
            $this->setData('config', array_replace_recursive(['actions' => $this->actions], $this->getConfiguration()));
            $this->actions->jsonSerialize();
            if (empty($this->actions->getOptions())) {
                $this->setData('config', array_replace_recursive(['actionDisable' => true], $this->getData('config')));
            }
        }

        parent::prepare();
    }
}
