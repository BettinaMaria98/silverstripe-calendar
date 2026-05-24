<?php

namespace Dynamic\Calendar\Controller;

use PageController;
use Carbon\Carbon;
use Dynamic\Calendar\Model\EventException;
use Exception;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\i18n\i18n;
use SilverStripe\ORM\FieldType\DBField;

/**
 * Class EventPageController
 * @package Dynamic\Calendar\Controller
 */
class EventPageController extends PageController
{
    /**
     * @var array
     */
    private static $allowed_actions = [
        'occurrences',
        'next-occurrence',
    ];

    private ?string $instanceDate = null;
    private ?EventException $currentException = null;
    private bool $instanceLoaded = false;

    private function loadInstance(): void
    {
        if ($this->instanceLoaded) {
            return;
        }
        $this->instanceLoaded = true;
        $instanceDate = $this->getRequest()->getVar('instance');
        if ($instanceDate && $this->dataRecord->eventRecurs()) {
            $this->instanceDate = $instanceDate;
            $this->currentException = EventException::get()->filter([
                'OriginalEventID' => $this->dataRecord->ID,
                'InstanceDate'    => $instanceDate,
            ])->first();
        }
    }

    /**
     * Format a time value using the configured timezone
     */
    private function formatTime(?string $raw): string
    {
        if (!$raw) {
            return '';
        }
        $tz = CalendarController::config()->get('timezone') ?: date_default_timezone_get();
        return Carbon::parse($raw, $tz)->format('H:i');
    }

    public function StartDate(): mixed
    {
        $this->loadInstance();
        if ($this->instanceDate) {
            $date = $this->currentException?->hasOverride('StartDate')
                ? $this->currentException->getOverride('StartDate')
                : $this->instanceDate;
            return DBField::create_field('Date', $date);
        }
        return $this->dataRecord->dbObject('StartDate');
    }

    public function EndDate(): mixed
    {
        $this->loadInstance();
        if ($this->currentException?->hasOverride('EndDate')) {
            return DBField::create_field('Date', $this->currentException->getOverride('EndDate'));
        }
        return $this->dataRecord->dbObject('EndDate');
    }

    public function FormattedStartTime(): string
    {
        $this->loadInstance();
        $raw = $this->currentException?->hasOverride('StartTime')
            ? $this->currentException->getOverride('StartTime')
            : $this->dataRecord->StartTime;
        return $this->formatTime($raw);
    }

    public function FormattedEndTime(): string
    {
        $this->loadInstance();
        $raw = $this->currentException?->hasOverride('EndTime')
            ? $this->currentException->getOverride('EndTime')
            : $this->dataRecord->EndTime;
        return $this->formatTime($raw);
    }

    public function Title(): mixed
    {
        $this->loadInstance();
        if ($this->currentException?->hasOverride('Title')) {
            return $this->currentException->getOverride('Title');
        }
        return $this->dataRecord->Title;
    }

    public function Content(): mixed
    {
        $this->loadInstance();
        if ($this->currentException?->hasOverride('Content')) {
            return DBField::create_field('HTMLText', $this->currentException->getOverride('Content'));
        }
        return $this->dataRecord->dbObject('Content');
    }

    public function AllDay(): mixed
    {
        $this->loadInstance();
        if ($this->currentException?->hasOverride('AllDay')) {
            return $this->currentException->getOverride('AllDay');
        }
        return $this->dataRecord->AllDay;
    }

    public function TimeSuffix(): string
    {
        return str_starts_with(i18n::get_locale(), 'de') ? 'Uhr' : '';
    }

    /**
     * Get event occurrences for a date range (AJAX endpoint)
     *
     * @param HTTPRequest $request
     * @return HTTPResponse
     */
    public function occurrences(HTTPRequest $request): HTTPResponse
    {
        $response = HTTPResponse::create();
        $response->addHeader('Content-Type', 'application/json');

        if (!$this->dataRecord->eventRecurs()) {
            $response->setBody(json_encode(['occurrences' => []]));
            return $response;
        }

        $fromDate = $request->getVar('from') ?: Carbon::now()->format('Y-m-d');
        $toDate = $request->getVar('to') ?: Carbon::now()->addMonths(3)->format('Y-m-d');

        try {
            $occurrences = [];
            $generator = $this->dataRecord->getOccurrences($fromDate, $toDate, 50);

            foreach ($generator as $occurrence) {
                $occurrences[] = [
                    'title'      => $occurrence->Title,
                    'date'       => $occurrence->StartDate ? Carbon::parse($occurrence->StartDate)->format('M j, Y') : '',
                    'time'       => $occurrence->StartTime ? Carbon::parse($occurrence->StartTime)->format('g:i A') : '',
                    'isModified' => $occurrence->isModified(),
                    'isDeleted'  => $occurrence->isDeleted(),
                    'link'       => $occurrence->Link ?? $this->dataRecord->Link(),
                ];
            }

            $response->setBody(json_encode(['occurrences' => $occurrences]));
        } catch (Exception) {
            $response->setStatusCode(500);
            $response->setBody(json_encode(['error' => 'Failed to load occurrences']));
        }

        return $response;
    }

    /**
     * Get next occurrence (AJAX endpoint)
     *
     * @param HTTPRequest $request
     * @return HTTPResponse
     */
    public function nextOccurrence(HTTPRequest $request): HTTPResponse
    {
        $response = HTTPResponse::create();
        $response->addHeader('Content-Type', 'application/json');

        if (!$this->dataRecord->eventRecurs()) {
            $response->setBody(json_encode(['nextOccurrence' => null]));
            return $response;
        }

        try {
            $nextOccurrence = $this->dataRecord->getNextOccurrence();

            if ($nextOccurrence) {
                $data = [
                    'nextOccurrence' => [
                        'date'       => Carbon::parse($nextOccurrence->StartDate)->format('M j, Y'),
                        'time'       => $nextOccurrence->StartTime
                            ? Carbon::parse($nextOccurrence->StartTime)->format('g:i A')
                            : null,
                        'isModified' => $nextOccurrence->isModified(),
                    ]
                ];
            } else {
                $data = ['nextOccurrence' => null];
            }

            $response->setBody(json_encode($data));
        } catch (Exception) {
            $response->setStatusCode(500);
            $response->setBody(json_encode(['error' => 'Failed to load next occurrence']));
        }

        return $response;
    }

    public function CurrentDate(): string
    {
        return Carbon::now()->format('Y-m-d');
    }

    public function CurrentFromDate(): string
    {
        return $this->getRequest()->getVar('from') ?: Carbon::now()->format('Y-m-d');
    }

    public function CurrentToDate(): string
    {
        return $this->getRequest()->getVar('to') ?: Carbon::now()->addMonths(3)->format('Y-m-d');
    }
}
