<?php

namespace Dynamic\Calendar\Model;

use InvalidArgumentException;
use Override;
use SilverStripe\Core\Validation\ValidationException;
use DateTime;
use IntlDateFormatter;
use SilverStripe\Core\Validation\ValidationResult;
use SilverStripe\i18n\i18n;
use Dynamic\Calendar\Extension\CalendarCacheInvalidation;
use Dynamic\Calendar\Page\EventPage;
use SilverStripe\Control\Director;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\DateField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\HTMLEditor\HTMLEditorField;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\TimeField;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;

/**
 * Event Exception
 *
 * Represents modifications or deletions to specific instances of a recurring event.
 * This allows individual occurrences to be customized without affecting the entire series.
 *
 * @package Dynamic\Calendar\Model
 *
 * @property string $InstanceDate
 * @property string $Action
 * @property string $ModifiedTitle
 * @property string $ModifiedContent
 * @property string $ModifiedStartTime
 * @property string $ModifiedEndTime
 * @property string $ModifiedStartDate
 * @property string $ModifiedEndDate
 * @property bool $ModifiedAllDay
 * @property string $Reason
 *
 * @method EventPage OriginalEvent()
 */
class EventException extends DataObject implements PermissionProvider
{
    use CalendarCacheInvalidation;

    /**
     * Check if validation should be skipped
     * More specific than Director::isDev() for better control
     *
     * @return bool
     */
    protected function shouldSkipValidation(): bool
    {
        // Skip validation during fixture loading or testing
        return Director::isDev() ||
            (defined('SS_ENVIRONMENT_TYPE') && SS_ENVIRONMENT_TYPE === 'test') ||
            $this->config()->get('skip_validation');
    }

    /**
     * @var string
     */
    private static string $table_name = 'EventException';

    /**
     * @var string
     */
    private static string $singular_name = 'Event Exception';

    /**
     * @var string
     */
    private static string $plural_name = 'Event Exceptions';

    /**
     * @var array
     */
    private static array $db = [
        'InstanceDate' => 'Date',
        'Action' => 'Enum("MODIFIED,DELETED","MODIFIED")',
        'ModifiedTitle' => 'Varchar(255)',
        'ModifiedContent' => 'HTMLText',
        'ModifiedStartTime' => 'Time',
        'ModifiedEndTime' => 'Time',
        'ModifiedStartDate' => 'Date',
        'ModifiedEndDate' => 'Date',
        'ModifiedAllDay' => 'Boolean',
        'Reason' => 'Text',
        // Add 'IsModified' property to track modification state
        'IsModified' => 'Boolean',
    ];

    /**
     * @var array
     */
    private static array $has_one = [
        'OriginalEvent' => EventPage::class,
    ];

    /**
     * @var array
     */
    private static array $indexes = [
        'EventDate' => [
            'type' => 'index',
            'columns' => ['OriginalEventID', 'InstanceDate'],
        ],
    ];

    /**
     * @var array
     */
    private static array $searchable_fields = [
        'OriginalEvent.Title',
        'InstanceDate',
        'Action',
        'ModifiedTitle',
    ];

    /**
     * @var string
     */
    private static string $default_sort = 'InstanceDate ASC';

    /**
     * Translated column headings for the GridField summary (static properties can't call _t(),
     * so the translated labels are applied here instead of directly in $summary_fields)
     *
     * @return array
     */
    #[Override]
    public function summaryFields(): array
    {
        return [
            'OriginalEvent.Title' => _t('Dynamic\Calendar\Model\EventException.OriginalEvent.Title', 'Event'),
            'InstanceDate' => _t('Dynamic\Calendar\Model\EventException.InstanceDate', 'Instance Date'),
            'ActionNice' => _t('Dynamic\Calendar\Model\EventException.ActionNice', 'Action'),
            'ModifiedTitle' => _t('Dynamic\Calendar\Model\EventException.ModifiedTitle', 'Modified Title'),
        ];
    }

    /**
     * Mapping of fields that can be overridden
     *
     * @var array
     */
    private static array $overridable_fields = [
        'Title' => 'ModifiedTitle',
        'Content' => 'ModifiedContent',
        'StartTime' => 'ModifiedStartTime',
        'EndTime' => 'ModifiedEndTime',
        'StartDate' => 'ModifiedStartDate',
        'EndDate' => 'ModifiedEndDate',
        'AllDay' => 'ModifiedAllDay',
    ];

    /**
     * Check if a specific property has an override value
     *
     * @param string $property
     * @return bool
     */
    public function hasOverride(string $property): bool
    {
        $overridableFields = $this->config()->get('overridable_fields');

        if (!isset($overridableFields[$property])) {
            return false;
        }

        $overrideField = $overridableFields[$property];
        $value = $this->$overrideField;

        // For time fields, check if value is explicitly set (not NULL and not empty string)
        // This allows users to intentionally set midnight (00:00:00) as an override
        if (in_array($overrideField, ['ModifiedStartTime', 'ModifiedEndTime'])) {
            return $value !== null && $value !== '';
        }

        // Check if the override field has a value
        return !empty($value);
    }

    /**
     * Get the override value for a specific property
     *
     * @param string $property
     * @return mixed|null
     */
    public function getOverride(string $property)
    {
        if (!$this->hasOverride($property)) {
            return null;
        }

        $overridableFields = $this->config()->get('overridable_fields');
        $overrideField = $overridableFields[$property];

        return $this->$overrideField;
    }

    /**
     * Set an override value for a specific property
     *
     * @param string $property
     * @param mixed $value
     * @return $this
     */
    public function setOverride(string $property, $value): self
    {
        $overridableFields = $this->config()->get('overridable_fields');

        if (!isset($overridableFields[$property])) {
            throw new InvalidArgumentException("Property '{$property}' cannot be overridden");
        }

        $overrideField = $overridableFields[$property];
        $this->$overrideField = $value;

        return $this;
    }

    /**
     * Get all override values as an array
     *
     * @return array
     */
    public function getOverrides(): array
    {
        $overrides = [];
        $overridableFields = $this->config()->get('overridable_fields');

        foreach ($overridableFields as $property => $overrideField) {
            if ($this->hasOverride($property)) {
                $overrides[$property] = $this->getOverride($property);
            }
        }

        return $overrides;
    }

    /**
     * Check if this exception represents a deleted instance
     *
     * @return bool
     */
    public function isDeleted(): bool
    {
        return $this->Action === 'DELETED';
    }

    /**
     * Translated, human-readable label for the Action field (used in summary_fields)
     *
     * @return string
     */
    public function getActionNice(): string
    {
        return $this->isDeleted()
            ? _t('Dynamic\Calendar\Model\EventException.ACTION_DELETED', 'Deleted')
            : _t('Dynamic\Calendar\Model\EventException.ACTION_MODIFIED', 'Modified');
    }

    /**
     * Check if this exception represents a modified instance
     *
     * @return bool
     */
    public function isModified(): bool
    {
        return $this->Action === 'MODIFIED';
    }

    /**
     * Get a human-readable description of this exception
     *
     * @return string
     */
    public function getDescription(): string
    {
        if ($this->isDeleted()) {
            return _t(
                'Dynamic\Calendar\Model\EventException.DELETED_OCCURRENCE',
                'Deleted occurrence on {date}',
                ['date' => $this->InstanceDate]
            );
        }

        if ($this->isModified()) {
            $changes = [];

            if ($this->hasOverride('Title')) {
                $changes[] = 'title';
            }
            if ($this->hasOverride('StartTime') || $this->hasOverride('EndTime')) {
                $changes[] = 'time';
            }
            if ($this->hasOverride('StartDate') || $this->hasOverride('EndDate')) {
                $changes[] = 'date';
            }
            if ($this->hasOverride('Content')) {
                $changes[] = 'content';
            }

            $changesText = !empty($changes) ? ' (' . implode(', ', $changes) . ')' : '';

            return "Modified occurrence on {$this->InstanceDate}{$changesText}";
        }

        return "Exception on {$this->InstanceDate}";
    }

    /**
     * Validation
     *
     * @return ValidationResult
     */
    #[Override]
    public function validate(): ValidationResult
    {
        $result = parent::validate();

        // Validate that we don't have duplicate exceptions for the same event and date
        if ($this->OriginalEventID && $this->InstanceDate) {
            $existing = static::get()->filter([
                'OriginalEventID' => $this->OriginalEventID,
                'InstanceDate' => $this->InstanceDate,
            ]);

            if ($this->ID) {
                $existing = $existing->exclude(['ID' => $this->ID]);
            }

            if ($existing->count() > 0) {
                $result->addError(
                    _t('Dynamic\Calendar\Model\EventException.DUPLICATE_EXCEPTION', 'An exception already exists for this event on this date'),
                    'DUPLICATE_EXCEPTION'
                );
            }
        }

        return $result;
    }

    /**
     * Validate the exception before writing
     */
    #[Override]
    protected function onBeforeWrite()
    {
        parent::onBeforeWrite();

        // Skip validation if we're in a test environment and this is a new record
        // This allows fixtures to be loaded without triggering premature validation
        if (!$this->isInDB() && $this->shouldSkipValidation()) {
            return;
        }

        // Ensure we have an original event
        if (!$this->OriginalEventID) {
            throw ValidationException::create(_t('Dynamic\Calendar\Model\EventException.NO_EVENT', 'An EventException must be associated with an event'));
        }

        // Ensure we have an instance date
        if (!$this->InstanceDate) {
            throw ValidationException::create(_t('Dynamic\Calendar\Model\EventException.NO_INSTANCE_DATE', 'An EventException must specify an instance date'));
        }

        // Ensure the original event exists and is recurring
        $originalEvent = $this->OriginalEvent();
        if (!$originalEvent || !$originalEvent->exists()) {
            throw ValidationException::create(_t('Dynamic\Calendar\Model\EventException.EVENT_NOT_FOUND', 'The associated event does not exist'));
        }

        if (!$originalEvent->eventRecurs()) {
            throw ValidationException::create(_t('Dynamic\Calendar\Model\EventException.NOT_RECURRING', 'EventExceptions can only be created for recurring events'));
        }
    }

    /**
     * Clear caches when exception is modified
     */
    #[Override]
    protected function onAfterWrite()
    {
        parent::onAfterWrite();

        // Clear parent event's instance cache
        if ($this->OriginalEvent()->exists()) {
            EventInstanceCache::clearEventCache($this->OriginalEvent());
        }

        // Clear JSON cache
        $this->clearCalendarJSONCache();
    }

    /**
     * Clear caches when exception is deleted
     */
    #[Override]
    protected function onAfterDelete()
    {
        parent::onAfterDelete();

        // Clear parent event's instance cache
        if ($this->OriginalEvent()->exists()) {
            EventInstanceCache::clearEventCache($this->OriginalEvent());
        }

        // Clear JSON cache
        $this->clearCalendarJSONCache();
    }

    /**
     * Check if this exception has any override values
     *
     * @return bool
     */
    protected function hasAnyOverrides(): bool
    {
        $overridableFields = $this->config()->get('overridable_fields');

        foreach ($overridableFields as $overrideField) {
            if (!empty($this->$overrideField)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Permission provider implementation
     *
     * @return array
     */
    public function providePermissions(): array
    {
        return [
            'EDIT_EVENT_EXCEPTIONS'   => _t('Dynamic\Calendar\Model\EventException.EDIT_PERMISSION', 'Edit event exceptions'),
            'DELETE_EVENT_EXCEPTIONS' => _t('Dynamic\Calendar\Model\EventException.DELETE_PERMISSION', 'Delete event exceptions'),
            'CREATE_EVENT_EXCEPTIONS' => _t('Dynamic\Calendar\Model\EventException.CREATE_PERMISSION', 'Create event exceptions'),
        ];
    }

    /**
     * @param null $member
     * @return bool
     */
    #[Override]
    public function canView($member = null): bool
    {
        if (Permission::check('ADMIN', 'any', $member)) {
            return true;
        }

        $originalEvent = $this->OriginalEvent();
        return $originalEvent && $originalEvent->exists() && $originalEvent->canView($member);
    }

    /**
     * @param null $member
     * @return bool
     */
    #[Override]
    public function canEdit($member = null): bool
    {
        if (Permission::check('ADMIN', 'any', $member) || Permission::check('EDIT_EVENT_EXCEPTIONS', 'any', $member)) {
            return true;
        }

        $originalEvent = $this->OriginalEvent();
        return $originalEvent && $originalEvent->exists() && $originalEvent->canEdit($member);
    }

    /**
     * @param null $member
     * @return bool
     */
    #[Override]
    public function canDelete($member = null): bool
    {
        if (
            Permission::check('ADMIN', 'any', $member)
            || Permission::check('DELETE_EVENT_EXCEPTIONS', 'any', $member)
        ) {
            return true;
        }

        $originalEvent = $this->OriginalEvent();
        return $originalEvent && $originalEvent->exists() && $originalEvent->canDelete($member);
    }

    /**
     * @return FieldList
     */
    #[Override]
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        // Remove the OriginalEventID field as it's managed by the relationship
        $fields->removeByName('OriginalEventID');

        // Add event selection dropdown
        $eventOptions = [];
        $events = EventPage::get()->filter(['Recursion:not' => ''])->sort(['Title' => 'ASC']);
        foreach ($events as $event) {
            $eventOptions[$event->ID] = $event->Title;
        }

        $eventField = DropdownField::create('OriginalEventID', _t('Dynamic\Calendar\Model\EventException.EVENT_FIELD', 'Event'))
            ->setSource($eventOptions)
            ->setDescription(_t('Dynamic\Calendar\Model\EventException.EVENT_FIELD_DESC', 'Select the recurring event this exception applies to'))
            ->setEmptyString(_t('Dynamic\Calendar\Model\EventException.EVENT_EMPTY', '-- Select an event --'));

        // Get the original event to populate instance dropdown
        $originalEvent = $this->OriginalEvent();
        $instanceOptions = [];

        if ($originalEvent && $originalEvent->exists() && $originalEvent->eventRecurs()) {
            // Get all instances from event start date up to 24 months in the future
            $startDate = $originalEvent->StartDate ? new DateTime($originalEvent->StartDate) : new DateTime('-12 months');
            $endDate = new DateTime('+24 months');
            $instances = $originalEvent->getOccurrences($startDate, $endDate);

            $dateFormatter = new IntlDateFormatter(
                i18n::get_locale(),
                IntlDateFormatter::FULL,
                IntlDateFormatter::NONE
            );

            foreach ($instances as $instance) {
                $instanceDate = $instance->getInstanceDate();
                $formattedDate = $instanceDate->format('Y-m-d');
                $displayDate = $dateFormatter->format($instanceDate);
                $instanceOptions[$formattedDate] = $displayDate;
            }

            // If this is an existing exception and its InstanceDate is not in the options, add it
            if ($this->exists() && $this->InstanceDate && !isset($instanceOptions[$this->InstanceDate])) {
                $savedDate = new DateTime($this->InstanceDate);
                $displayDate = $dateFormatter->format($savedDate);
                $instanceOptions[$this->InstanceDate] = _t(
                    'Dynamic\Calendar\Model\EventException.INSTANCE_SAVED_OPTION',
                    '{date} (saved)',
                    ['date' => $displayDate]
                );
            }
        }

        // Create instance selection field
        if (!empty($instanceOptions)) {
            $instanceField = DropdownField::create('InstanceDate', _t('Dynamic\Calendar\Model\EventException.INSTANCE_FIELD', 'Event Instance'))
                ->setSource($instanceOptions)
                ->setDescription(_t('Dynamic\Calendar\Model\EventException.INSTANCE_FIELD_DESC', 'Select the specific event instance this exception applies to'))
                ->setEmptyString(_t('Dynamic\Calendar\Model\EventException.INSTANCE_EMPTY', '-- Select an instance --'));

            // Set the current value if this is an existing record
            if ($this->exists() && $this->InstanceDate) {
                $instanceField->setValue($this->InstanceDate);
            }
        } else {
            // Fallback to date field if no instances available
            $instanceField = DateField::create('InstanceDate', _t('Dynamic\Calendar\Model\EventException.INSTANCE_DATE_FIELD', 'Instance Date'))
                ->setDescription(_t('Dynamic\Calendar\Model\EventException.INSTANCE_DATE_DESC', 'The date of the event instance this exception applies to'))
                ->setHTML5(true);
        }

        // Add more user-friendly field configuration
        $fields->addFieldsToTab('Root.Main', [
            $eventField,
            $instanceField,
            DropdownField::create('Action', _t('Dynamic\Calendar\Model\EventException.EXCEPTION_TYPE', 'Exception Type'))
                ->setSource([
                    'MODIFIED' => _t('Dynamic\Calendar\Model\EventException.MODIFY_INSTANCE', 'Modify this instance'),
                    'DELETED'  => _t('Dynamic\Calendar\Model\EventException.DELETE_INSTANCE', 'Delete this instance'),
                ])
                ->setDescription(_t('Dynamic\Calendar\Model\EventException.EXCEPTION_TYPE_DESC', 'Choose whether to modify or delete this specific event instance')),
            TextField::create('Reason', _t('Dynamic\Calendar\Model\EventException.REASON_FIELD', 'Reason'))
                ->setDescription(_t('Dynamic\Calendar\Model\EventException.REASON_DESC', 'Optional reason for this exception'))
        ]);

        // Group modification fields
        $fields->findOrMakeTab('Root.Modifications', _t('Dynamic\Calendar\Model\EventException.TAB_MODIFICATIONS', 'Modifications'));
        $fields->addFieldsToTab(
            'Root.Modifications',
            [
                TextField::create('ModifiedTitle', _t('Dynamic\Calendar\Model\EventException.MODIFIED_TITLE', 'Modified Title'))
                    ->setDescription(_t('Dynamic\Calendar\Model\EventException.MODIFIED_TITLE_DESC', 'Leave empty to use the original event title')),
                HTMLEditorField::create('ModifiedContent', _t('Dynamic\Calendar\Model\EventException.MODIFIED_CONTENT', 'Modified Content'))
                    ->setDescription(_t('Dynamic\Calendar\Model\EventException.MODIFIED_CONTENT_DESC', 'Leave empty to use the original event content')),
                DateField::create('ModifiedStartDate', _t('Dynamic\Calendar\Model\EventException.MODIFIED_START_DATE', 'Modified Start Date'))
                    ->setDescription(_t('Dynamic\Calendar\Model\EventException.MODIFIED_START_DATE_DESC', 'Leave empty to use the original start date'))
                    ->setHTML5(true),
                TimeField::create('ModifiedStartTime', _t('Dynamic\Calendar\Model\EventException.MODIFIED_START_TIME', 'Modified Start Time'))
                    ->setDescription(_t('Dynamic\Calendar\Model\EventException.MODIFIED_START_TIME_DESC', 'Leave empty to use the original start time. Set to 00:00:00 for midnight.'))
                    ->setAttribute('value', $this->ModifiedStartTime ?? ''),
                DateField::create('ModifiedEndDate', _t('Dynamic\Calendar\Model\EventException.MODIFIED_END_DATE', 'Modified End Date'))
                    ->setDescription(_t('Dynamic\Calendar\Model\EventException.MODIFIED_END_DATE_DESC', 'Leave empty to use the original end date'))
                    ->setHTML5(true),
                TimeField::create('ModifiedEndTime', _t('Dynamic\Calendar\Model\EventException.MODIFIED_END_TIME', 'Modified End Time'))
                    ->setDescription(_t('Dynamic\Calendar\Model\EventException.MODIFIED_END_TIME_DESC', 'Leave empty to use the original end time. Set to 00:00:00 for midnight.'))
                    ->setAttribute('value', $this->ModifiedEndTime ?? ''),
                CheckboxField::create('ModifiedAllDay', _t('Dynamic\Calendar\Model\EventException.ALL_DAY_EVENT', 'All Day Event'))
                    ->setDescription(_t('Dynamic\Calendar\Model\EventException.ALL_DAY_EVENT_DESC', 'Override the all-day setting for this instance')),
            ]
        );

        // Show modification fields only when Action is MODIFIED
        $modificationTab = $fields->fieldByName('Root.Modifications');
        if ($modificationTab) {
            $modificationTab->displayIf('Action')->isEqualTo('MODIFIED');
        }

        return $fields;
    }

    /**
     * Get a human-readable title for this exception
     *
     * @return string
     */
    #[Override]
    public function getTitle()
    {
        $event = $this->OriginalEvent();
        $eventTitle = $event && $event->exists() ? $event->Title : 'Unknown Event';
        $action = $this->Action === 'DELETED' ? 'Delete' : 'Modify';

        return "{$action} {$eventTitle} on {$this->InstanceDate}";
    }

    /**
     * @param null $member
     * @return bool
     */
    #[Override]
    public function canCreate($member = null, $context = []): bool
    {
        return Permission::check('ADMIN', 'any', $member)
            || Permission::check('CREATE_EVENT_EXCEPTIONS', 'any', $member);
    }

    /**
     * Find an exception for a specific event and date
     *
     * @param EventPage $event
     * @param string $instanceDate
     * @return EventException|null
     */
    public static function findForEventAndDate(EventPage $event, string $instanceDate): ?EventException
    {
        return static::get()->filter([
            'OriginalEventID' => $event->ID,
            'InstanceDate' => $instanceDate,
        ])->first();
    }

    /**
     * Create a deletion exception for a specific instance
     *
     * @param EventPage $event
     * @param string $instanceDate
     * @param string $reason
     * @return EventException
     */
    public static function createDeletion(EventPage $event, string $instanceDate, string $reason = ''): EventException
    {
        $exception = static::create([
            'OriginalEventID' => $event->ID,
            'InstanceDate' => $instanceDate,
            'Action' => 'DELETED',
            'Reason' => $reason,
        ]);

        $exception->write();

        return $exception;
    }

    /**
     * Create a modification exception for a specific instance
     *
     * @param EventPage $event
     * @param string $instanceDate
     * @param array $overrides
     * @param string $reason
     * @return EventException
     */
    public static function createModification(
        EventPage $event,
        string $instanceDate,
        array $overrides,
        string $reason = ''
    ): EventException {
        $exception = static::create([
            'OriginalEventID' => $event->ID,
            'InstanceDate' => $instanceDate,
            'Action' => 'MODIFIED',
            'Reason' => $reason,
        ]);

        foreach ($overrides as $property => $value) {
            $exception->setOverride($property, $value);
        }

        $exception->write();

        return $exception;
    }
}
