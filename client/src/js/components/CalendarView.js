// FullCalendar Integration for Dynamic SilverStripe Calendar
import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import listPlugin from '@fullcalendar/list';
import bootstrap5Plugin from '@fullcalendar/bootstrap5';
import interactionPlugin from '@fullcalendar/interaction';
import deLocale from '@fullcalendar/core/locales/de';
import { getRollingListWeekView } from './rollingListWeekView';

// Map language codes to FullCalendar locale objects
const FC_LOCALES = { de: deLocale };

// Shared constants
const RESIZE_DEBOUNCE_MS = 150;

export class CalendarView {
  constructor(element, options = {}) {
    this.element = element;

    // Store custom configuration from options and data attributes
    this.config = {
      ...this.getConfigFromElement(),
      ...options
    };

    // Detect locale from <html lang="..."> (e.g. "de-AT" -> "de")
    const htmlLang = document.documentElement.lang || 'en';
    const localeCode = htmlLang.toLowerCase().replace('_', '-').split('-')[0];
    const fcLocale = FC_LOCALES[localeCode];
    // If a 'from' filter is set in the URL, start the calendar there
    const urlParams = new URLSearchParams(window.location.search);
    const fromParam = urlParams.get('from');

    this.options = {
      plugins: [dayGridPlugin, timeGridPlugin, listPlugin, bootstrap5Plugin, interactionPlugin],
      themeSystem: 'bootstrap5',
      ...(fcLocale ? { locale: fcLocale } : {}),
      headerToolbar: this.getResponsiveHeaderToolbar(),

      // Responsive initial view - month on all screen sizes (no listWeek by default)
      initialView: this.config.defaultView || 'dayGridMonth',
      ...(fromParam ? { initialDate: fromParam } : {}),

      height: 'auto',
      aspectRatio: 1.8,
      eventDisplay: 'block',
      dayMaxEvents: true,
      moreLinkClick: 'popover',
      views: {
        listWeek: getRollingListWeekView()
      },

      // Block navigation to past months
      validRange: {
        start: new Date(new Date().getFullYear(), new Date().getMonth(), 1)
      },

      // Window resize handling for responsive behavior
      windowResizeDelay: 150
    };

    this.init();
  }

  init() {
    // Initialise before render() — datesSet fires synchronously during render
    this._viewStart = null;
    this._viewEnd   = null;
    this._viewTitle = '';

    const finalOptions = {
      ...this.options,
      events: (info, successCallback, failureCallback) => {
        this.fetchEvents(info, successCallback, failureCallback);
      },
      eventClick: (info) => this.handleEventClick(info),
      dateClick: (info) => this.handleDateClick(info),
      eventDidMount: (info) => this.handleEventDidMount(info),
      datesSet: (info) => this.handleDatesSet(info),
      eventsSet: (events) => this.handleEventsSet(events),
    };

    this.calendar = new Calendar(this.element, finalOptions);
    this.calendar.render();

    if (process.env.NODE_ENV === 'development') {
      window.fullCalendarInstance = this.calendar;
    }

    this.initializeMobileOptimizations();
  }

  getConfigFromElement() {
    const config = {};

    if (this.element.dataset.calendarId) {
      config.calendarId = this.element.dataset.calendarId;
    }

    if (this.element.dataset.defaultView) {
      config.initialView = this.element.dataset.defaultView;
    }

    if (this.element.dataset.eventsUrl) {
      config.eventsUrl = this.element.dataset.eventsUrl;
    }

    if (this.element.dataset.availableViews) {
      config.availableViews = this.element.dataset.availableViews.split(',').map(v => v.trim()).filter(Boolean);
    }

    return config;
  }

  async fetchEvents(info, successCallback, failureCallback) {
    const eventsUrl = this.config.eventsUrl;

    if (!eventsUrl) {
      console.error('Events URL not configured');
      failureCallback(new Error('Events URL not configured'));
      return;
    }

    const params = new URLSearchParams({
      start: info.startStr,
      end: info.endStr,
      format: 'json'
    });

    // Forward filter form values — iterate FormData directly to handle multi-select correctly
    const filterForm = document.querySelector('.calendar-filter-form');
    if (filterForm) {
      const skip = new Set(['action_doFilter', 'SecurityID', 'advanced']);
      for (const [key, value] of new FormData(filterForm).entries()) {
        if (value && !skip.has(key)) {
          params.append(key, value);
        }
      }
    }

    try {
      const response = await fetch(`${eventsUrl}?${params.toString()}`, {
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        }
      });

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }

      const events = await response.json();

      if (Array.isArray(events)) {
        successCallback(events);
      } else {
        console.error('Expected array of events, got:', events);
        failureCallback(new Error('Invalid events format'));
      }
    } catch (error) {
      console.error('Error fetching events:', error);
      failureCallback(error);
    }
  }

  getActiveFilters() {
    const filters = {};
    const filterForm = document.querySelector('.calendar-filter-form');
    if (filterForm) {
      const formData = new FormData(filterForm);
      for (let [key, value] of formData.entries()) {
        if (key !== 'action_doFilter' && key !== 'SecurityID' && key !== 'advanced') {
          filters[key] = value;
        }
      }
    }
    return filters;
  }

  handleEventClick(info) {
    info.jsEvent.preventDefault();
    const event = info.event;
    if (event.url) {
      window.location.href = event.url;
    } else {
      this.showEventPopup(event);
    }
  }

  handleDateClick(info) {
    const url = new URL(window.location);
    url.searchParams.set('date', info.dateStr);
    window.history.pushState({}, '', url);
  }

  handleEventDidMount(info) {
    const element = info.el;
    if (info.event.extendedProps.isRecurring) {
      element.classList.add('recurring-event');
    }
    if (info.event.extendedProps.summary) {
      element.title = info.event.extendedProps.summary;
    }
  }

  // datesSet fires first — store the range and title so eventsSet can filter correctly
  handleDatesSet(info) {
    this._viewStart = info.start;
    this._viewEnd   = info.end;
    this._viewTitle = info.view.title;

    const titleEl = this.getListContainer()?.querySelector('.js-cal-list-title');
    if (titleEl) titleEl.textContent = this._viewTitle;
  }

  // eventsSet fires after events are loaded — filter to the stored view range
  handleEventsSet(events) {
    if (!this._viewStart || !this._viewEnd) return;

    const visibleEvents = events
      .filter(e => e.start && e.start >= this._viewStart && e.start < this._viewEnd)
      .sort((a, b) => a.start - b.start);

    this.renderList(visibleEvents);
  }

  getListContainer() {
    return this.element.closest('.calendar-split')?.querySelector('.calendar-split__list') ?? null;
  }

  escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = String(str ?? '');
    return div.innerHTML;
  }

  renderList(events) {
    const listEl = this.getListContainer()?.querySelector('.js-cal-event-list');
    if (!listEl) return;

    if (events.length === 0) {
      listEl.innerHTML = '<li class="cal-event-list__empty">Keine Veranstaltungen.</li>';
      return;
    }

    const locale = (document.documentElement.lang || 'de-AT').replace('_', '-');

    listEl.innerHTML = events.map(event => {
      const dateStr = event.start.toLocaleDateString(locale, {
        day: '2-digit', month: '2-digit', year: 'numeric'
      });
      const timeStr = event.allDay
        ? ''
        : event.start.toLocaleTimeString(locale, { hour: '2-digit', minute: '2-digit' });
      const dateLine = timeStr ? `${dateStr} &middot; ${timeStr}` : dateStr;
      const href = event.url ? this.escapeHtml(event.url) : '#';

      return `<li class="cal-event-list__item">
        <a href="${href}" class="cal-event-list__link">
          <span class="cal-event-list__date">${dateLine}</span>
          <span class="cal-event-list__title">${this.escapeHtml(event.title)}</span>
        </a>
      </li>`;
    }).join('');
  }

  getResponsiveHeaderToolbar() {
    const views = this.config.availableViews ?? ['dayGridMonth', 'timeGridWeek', 'listWeek'];
    const viewsStr = views.join(',');
    const isSmallScreen = window.innerWidth < 768;

    if (isSmallScreen) {
      // On mobile use the first available view
      return {
        left: 'prev,next',
        center: 'title',
        right: views[0] ?? 'dayGridMonth'
      };
    }

    return {
      left: 'prev,next today',
      center: 'title',
      right: viewsStr
    };
  }

  initializeMobileOptimizations() {
    let resizeTimeout;
    let currentBreakpoint = this.getCurrentBreakpoint();

    this.resizeHandler = () => {
      clearTimeout(resizeTimeout);
      resizeTimeout = setTimeout(() => {
        const newBreakpoint = this.getCurrentBreakpoint();

        if (newBreakpoint !== currentBreakpoint) {
          currentBreakpoint = newBreakpoint;
          this.calendar.setOption('headerToolbar', this.getResponsiveHeaderToolbar());

          if (newBreakpoint === 'mobile') {
            const views = this.config.availableViews ?? ['dayGridMonth'];
            const currentView = this.calendar.view.type;
            if (currentView === 'dayGridMonth' || currentView === 'timeGridWeek') {
              this.calendar.changeView(views[0] ?? 'dayGridMonth');
            }
          }
        }

        this.calendar.updateSize();
      }, RESIZE_DEBOUNCE_MS);
    };

    window.addEventListener('resize', this.resizeHandler);
  }

  getCurrentBreakpoint() {
    const width = window.innerWidth;
    if (width < 768) return 'mobile';
    if (width < 1200) return 'tablet';
    return 'desktop';
  }

  showEventPopup(event) {
    const popup = document.createElement('div');
    popup.className = 'event-popup position-fixed';
    popup.style.cssText = `
      top: 50%; left: 50%; transform: translate(-50%, -50%);
      background: white; padding: 1rem; border-radius: 0.5rem;
      box-shadow: 0 4px 12px rgba(0,0,0,0.15); z-index: 1060;
      max-width: 400px; width: 90%;
    `;

    popup.innerHTML = `
      <div class="d-flex justify-content-between align-items-start mb-2">
        <h5 class="mb-0">${this.escapeHtml(event.title)}</h5>
        <button type="button" class="btn-close" onclick="this.closest('.event-popup').remove()"></button>
      </div>
      <p class="text-muted mb-2">
        <i class="bi bi-calendar"></i> ${event.start.toLocaleDateString()}
        ${event.start.toLocaleTimeString()}
      </p>
      ${event.extendedProps.summary ? `<p>${this.escapeHtml(event.extendedProps.summary)}</p>` : ''}
    `;

    document.body.appendChild(popup);

    const backdrop = document.createElement('div');
    backdrop.className = 'position-fixed';
    backdrop.style.cssText = 'top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:1059;';
    backdrop.onclick = () => {
      popup.remove();
      backdrop.remove();
    };
    document.body.appendChild(backdrop);
  }

  destroy() {
    if (this.resizeHandler) {
      window.removeEventListener('resize', this.resizeHandler);
      this.resizeHandler = null;
    }

    if (this.calendar) {
      this.calendar.destroy();
      this.calendar = null;
    }
  }
}