<?php

namespace Mautic\LeadBundle\Segment\Decorator\Date\Other;

use Doctrine\DBAL\Query\Expression\CompositeExpression;
use Mautic\LeadBundle\Segment\ContactSegmentFilterCrate;
use Mautic\LeadBundle\Segment\Decorator\Date\DateOptionParameters;
use Mautic\LeadBundle\Segment\Decorator\DateDecorator;
use Mautic\LeadBundle\Segment\Decorator\FilterDecoratorInterface;

class DateRelativeInterval implements FilterDecoratorInterface
{
    /**
     * @param string $originalValue
     */
    public function __construct(
        private DateDecorator $dateDecorator,
        private $originalValue,
        private DateOptionParameters $dateOptionParameters,
    ) {
    }

    /**
     * @return string|null
     */
    public function getField(ContactSegmentFilterCrate $contactSegmentFilterCrate)
    {
        return $this->dateDecorator->getField($contactSegmentFilterCrate);
    }

    public function getTable(ContactSegmentFilterCrate $contactSegmentFilterCrate): string
    {
        return $this->dateDecorator->getTable($contactSegmentFilterCrate);
    }

    /**
     * @return string
     */
    public function getOperator(ContactSegmentFilterCrate $contactSegmentFilterCrate)
    {
        if ('=' === $contactSegmentFilterCrate->getOperator()) {
            return 'like';
        }
        if ('!=' === $contactSegmentFilterCrate->getOperator()) {
            return 'notLike';
        }

        return $this->dateDecorator->getOperator($contactSegmentFilterCrate);
    }

    /**
     * @param array|string $argument
     *
     * @return array|string
     */
    public function getParameterHolder(ContactSegmentFilterCrate $contactSegmentFilterCrate, $argument)
    {
        return $this->dateDecorator->getParameterHolder($contactSegmentFilterCrate, $argument);
    }

    /**
     * @return array|bool|float|string|null
     */
    public function getParameterValue(ContactSegmentFilterCrate $contactSegmentFilterCrate): mixed
    {
        // For relative intervals like "-5 minutes", we should use current time, not midnight
        if ($this->shouldUseCurrentTime($this->originalValue)) {
            $timezone = $this->dateOptionParameters->hasTimePart() ? 'UTC' : 'UTC';
            $date = new \Mautic\CoreBundle\Helper\DateTimeHelper(new \DateTime('now', new \DateTimeZone($timezone)), null, $timezone);
        } else {
            $date = $this->dateOptionParameters->getDefaultDate();
        }
        
        $date->modify($this->originalValue);

        $operator = $this->getOperator($contactSegmentFilterCrate);
        $format = $this->dateOptionParameters->hasTimePart() ? 'Y-m-d H:i:s' : 'Y-m-d';
        if ('like' === $operator || 'notLike' === $operator) {
            $format .= '%';
        }

        return $date->toLocalString($format);
    }
    
    /**
     * Determine if we should use current time instead of midnight for relative intervals
     */
    private function shouldUseCurrentTime(string $interval): bool
    {
        // Check if the interval contains time units (minutes, hours, seconds)
        $timeUnits = ['minute', 'hour', 'second'];
        
        foreach ($timeUnits as $unit) {
            if (str_contains(strtolower($interval), $unit)) {
                return true;
            }
        }
        
        return false;
    }

    public function getQueryType(ContactSegmentFilterCrate $contactSegmentFilterCrate): string
    {
        return $this->dateDecorator->getQueryType($contactSegmentFilterCrate);
    }

    public function getAggregateFunc(ContactSegmentFilterCrate $contactSegmentFilterCrate): string|bool
    {
        return $this->dateDecorator->getAggregateFunc($contactSegmentFilterCrate);
    }

    public function getWhere(ContactSegmentFilterCrate $contactSegmentFilterCrate): CompositeExpression|string|null
    {
        return $this->dateDecorator->getWhere($contactSegmentFilterCrate);
    }
}
