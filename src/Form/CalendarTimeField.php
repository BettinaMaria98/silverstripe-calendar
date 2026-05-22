<?php

namespace Dynamic\Calendar\Form;

use Override;
use SilverStripe\Forms\TimeField;

/**
 * Class CalendarTimeField
 * @package Dynamic\Calendar\Form
 */
class CalendarTimeField extends TimeField
{
    /**
     * @return string
     */
    #[Override]
    public function getFormattedValue(): string
    {
        /**
         * @deprecated FormField::Value() has been deprecated. It will be replaced by getFormattedValue() and getValue().
         * See: https://docs.silverstripe.org/en/5/changelogs/5.4.0/#deprecated-api
         */
        $localised = $this->internalToFrontend($this->value);
        if ($localised) {
            return $localised;
        }

        return '';
    }
}
