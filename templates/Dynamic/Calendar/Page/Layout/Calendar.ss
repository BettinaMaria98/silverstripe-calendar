<div class="page-calendar">
  <div class="container">
    <div class="row">
      <div class="col-12">
      <!-- Page Header -->
      <header class="page-header mb-4">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
          <div>
            <h1 class="display-4">$Title</h1>
            <% if $Content %>
              <div class="lead">$Content</div>
            <% end_if %>
          </div>
        </div>
      </header>

      <!-- Calendar Action Toolbar -->
      <div class="calendar-toolbar mb-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">

          <!-- Filter and Subscribe Actions -->
          <div class="toolbar-actions d-flex gap-2">
            <% if $FilterForm %>
            <!-- Filter Toggle Button -->
            <button type="button" class="btn btn-outline-secondary js-toggle-filters"
                    aria-expanded="false"
                    data-bs-toggle="collapse"
                    data-bs-target="#calendar-filters"
                    aria-controls="calendar-filters">
              <i class="bi bi-funnel me-2"></i><%t Dynamic\Calendar\Page\Calendar.FILTER_EVENTS_BUTTON 'Filter Events' %>
              <i class="bi bi-chevron-down ms-2 filter-chevron"></i>
            </button>
            <% end_if %>

            <!-- Subscribe Button -->
            <button type="button" class="btn btn-outline-primary js-subscribe-calendar"
                    data-calendar-url="$Link"
                    data-bs-toggle="modal"
                    data-bs-target="#subscribeModal">
              <i class="bi bi-calendar-plus me-2"></i><%t Dynamic\Calendar\Page\Calendar.SUBSCRIBE_BUTTON 'Subscribe' %>
            </button>
          </div>

          <!-- Additional toolbar items could go here in the future -->
          <div class="toolbar-secondary">
            <!-- Space for future enhancements -->
          </div>
        </div>
      </div>

      <!-- Collapsible Filter Panel -->
      <% if $FilterForm %>
      <div class="collapse calendar-filter-collapse" id="calendar-filters">
        $FilterForm
      </div>
      <% end_if %>

      <!-- FullCalendar View -->
      <div id="fullcalendar-view" class="calendar-view-section"
           data-calendar-id="$ID"
           data-events-url="$Link/events"
           data-default-view="dayGridMonth">
        <div class="card">
          <div class="card-body">
            <div id="fullcalendar" style="min-height: 600px;"></div>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>
</div>

<!-- Elemental Area -->
<% if $ElementalArea %>
<div class="element-area main-element-area">
  $ElementalArea
</div>
<% end_if %>

<!-- ICS Subscription Modal -->
<div class="modal fade" id="subscribeModal" tabindex="-1" aria-labelledby="subscribeModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="subscribeModalLabel">
          <i class="bi bi-calendar-plus me-2"></i><%t Dynamic\Calendar\Page\Calendar.SUBSCRIBE_MODAL_TITLE 'Subscribe to Calendar' %>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="mb-3"><%t Dynamic\Calendar\Page\Calendar.SUBSCRIBE_MODAL_INTRO 'Subscribe to receive automatic calendar updates in your calendar app:' %></p>

        <!-- Subscription URL Display -->
        <div class="mb-3">
          <label for="subscription-url" class="form-label"><%t Dynamic\Calendar\Page\Calendar.SUBSCRIPTION_URL_LABEL 'Subscription URL:' %></label>
          <div class="input-group">
            <input type="text" class="form-control" id="subscription-url" readonly>
            <button type="button" class="btn btn-outline-secondary js-copy-url" title="<%t Dynamic\Calendar\Page\Calendar.COPY_URL_BUTTON 'Copy URL' %>">
              <i class="bi bi-clipboard"></i>
            </button>
          </div>
        </div>

        <div class="subscription-instructions">
          <h6><%t Dynamic\Calendar\Page\Calendar.HOW_TO_SUBSCRIBE 'How to subscribe:' %></h6>
          <div class="accordion accordion-flush" id="subscriptionInstructions">
            <div class="accordion-item">
              <h2 class="accordion-header" id="google-heading">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                        data-bs-target="#google-instructions" aria-expanded="false" aria-controls="google-instructions">
                  <%t Dynamic\Calendar\Page\Calendar.GOOGLE_CALENDAR 'Google Calendar' %>
                </button>
              </h2>
              <div id="google-instructions" class="accordion-collapse collapse" aria-labelledby="google-heading">
                <div class="accordion-body">
                  <ol>
                    <li><%t Dynamic\Calendar\Page\Calendar.GOOGLE_STEP_1 'Copy the subscription URL above' %></li>
                    <li><%t Dynamic\Calendar\Page\Calendar.GOOGLE_STEP_2 'Open Google Calendar' %></li>
                    <li><%t Dynamic\Calendar\Page\Calendar.GOOGLE_STEP_3 'On the left side, click the "+" next to "Other calendars"' %></li>
                    <li><%t Dynamic\Calendar\Page\Calendar.GOOGLE_STEP_4 'Select "From URL"' %></li>
                    <li><%t Dynamic\Calendar\Page\Calendar.GOOGLE_STEP_5 'Paste the URL and click "Add calendar"' %></li>
                  </ol>
                </div>
              </div>
            </div>

            <div class="accordion-item">
              <h2 class="accordion-header" id="outlook-heading">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                        data-bs-target="#outlook-instructions" aria-expanded="false" aria-controls="outlook-instructions">
                  <%t Dynamic\Calendar\Page\Calendar.MICROSOFT_OUTLOOK 'Microsoft Outlook' %>
                </button>
              </h2>
              <div id="outlook-instructions" class="accordion-collapse collapse" aria-labelledby="outlook-heading">
                <div class="accordion-body">
                  <ol>
                    <li><%t Dynamic\Calendar\Page\Calendar.OUTLOOK_STEP_1 'Copy the subscription URL above' %></li>
                    <li><%t Dynamic\Calendar\Page\Calendar.OUTLOOK_STEP_2 'Open Outlook Calendar' %></li>
                    <li><%t Dynamic\Calendar\Page\Calendar.OUTLOOK_STEP_3 'Click "Add calendar" then "From internet"' %></li>
                    <li><%t Dynamic\Calendar\Page\Calendar.OUTLOOK_STEP_4 'Paste the URL and click "OK"' %></li>
                  </ol>
                </div>
              </div>
            </div>

            <div class="accordion-item">
              <h2 class="accordion-header" id="apple-heading">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                        data-bs-target="#apple-instructions" aria-expanded="false" aria-controls="apple-instructions">
                  <%t Dynamic\Calendar\Page\Calendar.APPLE_CALENDAR 'Apple Calendar' %>
                </button>
              </h2>
              <div id="apple-instructions" class="accordion-collapse collapse" aria-labelledby="apple-heading">
                <div class="accordion-body">
                  <ol>
                    <li><%t Dynamic\Calendar\Page\Calendar.APPLE_STEP_1 'Copy the subscription URL above' %></li>
                    <li><%t Dynamic\Calendar\Page\Calendar.APPLE_STEP_2 'Open Calendar app' %></li>
                    <li><%t Dynamic\Calendar\Page\Calendar.APPLE_STEP_3 'File then New Calendar Subscription' %></li>
                    <li><%t Dynamic\Calendar\Page\Calendar.APPLE_STEP_4 'Paste the URL and click "Subscribe"' %></li>
                  </ol>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><%t Dynamic\Calendar\Page\Calendar.CLOSE_BUTTON 'Close' %></button>
        <a href="#" class="btn btn-success js-subscribe-app me-2">
          <i class="bi bi-calendar-plus me-2"></i><%t Dynamic\Calendar\Page\Calendar.SUBSCRIBE_IN_APP 'Subscribe in App' %>
        </a>
        <button type="button" class="btn btn-primary js-copy-url">
          <i class="bi bi-clipboard me-2"></i><%t Dynamic\Calendar\Page\Calendar.COPY_URL_BUTTON 'Copy URL' %>
        </button>
      </div>
    </div>
  </div>
</div>

<% require css('dynamic/silverstripe-calendar:client/dist/css/calendar.bundle.css') %>
<% require javascript('dynamic/silverstripe-calendar:client/dist/js/vendors.bundle.js') %>
<% require javascript('dynamic/silverstripe-calendar:client/dist/js/calendar.bundle.js') %>
