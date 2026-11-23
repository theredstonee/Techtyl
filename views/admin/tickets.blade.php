@php
  $t = static fn (string $key, array $replace = []): string => \Pterodactyl\BlueprintFramework\Extensions\{identifier}\I18n::t('tickets.' . $key, $replace);
  $ticketLabels = ['open' => 'label-danger', 'answered' => 'label-success', 'waiting' => 'label-warning', 'closed' => 'label-default'];
@endphp

@if (empty($ticketsReady))
  <div class="alert alert-warning">
    {{ $t('tables_missing_before') }} <code>php artisan migrate --force</code> {{ $t('tables_missing_after') }}
  </div>
@elseif ($ticketDetail !== null)
  {{-- Detailansicht: ?tab=tickets&ticket=<id> --}}
  <p><a href="{{ $root }}?tab=tickets" class="btn btn-default btn-sm"><i class="fa fa-angle-left"></i> {{ $t('back_all') }}</a></p>

  <div class="row">
    <div class="col-md-8">
      <div class="box">
        <div class="box-header with-border">
          <h3 class="box-title">#{{ $ticketDetail['ticket']->id }} {{ $ticketDetail['ticket']->subject }}</h3>
          <div class="box-tools">
            <span class="label {{ $ticketLabels[$ticketDetail['ticket']->status] ?? 'label-default' }}">{{ $ticketStatuses[$ticketDetail['ticket']->status] ?? $ticketDetail['ticket']->status }}</span>
          </div>
        </div>
        <div class="box-body techtyl-ticket-thread">
          @foreach ($ticketDetail['messages'] as $message)
            @if ($message['event'] !== null)
              <p class="text-muted small techtyl-ticket-event">
                <i class="fa fa-info-circle"></i> {{ $message['author'] }}: {{ $message['event'] }} <span>({{ $message['at'] }})</span>
              </p>
            @else
              <div class="well well-sm techtyl-ticket-message @if ($message['team']) techtyl-ticket-message-team @endif">
                <p>
                  <strong>{{ $message['author'] }}</strong>
                  @if ($message['team'])
                    <span class="label label-primary">{{ $t('badge_team') }}</span>
                  @else
                    <span class="label label-default">{{ $t('badge_user') }}</span>
                  @endif
                  <span class="text-muted small">{{ $message['at'] }}</span>
                </p>
                <p>
                  @foreach ($message['lines'] as $line)
                    {{ $line }}<br>
                  @endforeach
                </p>
                @foreach ($message['attachments'] as $attachment)
                  <a href="{{ $attachment['url'] }}" target="_blank" rel="noopener noreferrer" title="{{ $attachment['label'] }}">
                    <img src="{{ $attachment['url'] }}" alt="{{ $t('attachment_alt', ['size' => $attachment['label']]) }}" class="img-thumbnail" width="220" loading="lazy">
                  </a>
                @endforeach
              </div>
            @endif
          @endforeach
        </div>
      </div>

      <div class="box">
        <div class="box-header with-border"><h3 class="box-title">{{ $t('reply_heading') }}</h3></div>
        <form method="POST" action="{{ $root }}">
          {{ csrf_field() }}
          <input type="hidden" name="action" value="tickets.reply">
          <input type="hidden" name="ticket" value="{{ $ticketDetail['ticket']->id }}">
          <div class="box-body">
            <div class="form-group">
              <label for="techtyl-ticket-body">{{ $t('reply_to', ['user' => $ticketDetail['ticket']->username]) }}</label>
              <textarea class="form-control" id="techtyl-ticket-body" name="body" rows="7" maxlength="5000" required>{{ old('body') }}</textarea>
              <p class="text-muted small">{{ $t('reply_hint') }}</p>
            </div>
            <div class="form-group">
              <label for="techtyl-ticket-then">{{ $t('then_label') }}</label>
              <select class="form-control" id="techtyl-ticket-then" name="then">
                <option value="answered">{{ $t('team_status_answered') }}</option>
                <option value="waiting">{{ $t('then_waiting') }}</option>
                <option value="closed">{{ $t('team_status_closed') }}</option>
              </select>
            </div>
          </div>
          <div class="box-footer">
            <button type="submit" class="btn btn-primary pull-right">{{ $t('send_reply') }}</button>
          </div>
        </form>
      </div>
    </div>

    <div class="col-md-4">
      <div class="box">
        <div class="box-header with-border"><h3 class="box-title">{{ $t('details_heading') }}</h3></div>
        <div class="box-body table-responsive no-padding">
          <table class="table">
            <tr>
              <th>{{ $t('field_user') }}</th>
              <td><a href="/admin/users/view/{{ $ticketDetail['ticket']->user_id }}">{{ $ticketDetail['ticket']->username }}</a><br><span class="text-muted small">{{ $ticketDetail['ticket']->email }}</span></td>
            </tr>
            <tr>
              <th>{{ $t('field_category') }}</th>
              <td>{{ $ticketDetail['ticket']->category ?? $t('none_category') }}</td>
            </tr>
            <tr>
              <th>{{ $t('field_server') }}</th>
              <td>
                @if ($ticketDetail['ticket']->server_id !== null)
                  <a href="/admin/servers/view/{{ $ticketDetail['ticket']->server_id }}">{{ $ticketDetail['ticket']->server }}</a>
                @else
                  <span class="text-muted">{{ $t('none_server') }}</span>
                @endif
              </td>
            </tr>
            <tr>
              <th>{{ $t('opened') }}</th>
              <td>{{ $ticketDetail['created'] }}</td>
            </tr>
            <tr>
              <th>{{ $t('last_message') }}</th>
              <td>{{ $ticketDetail['updated'] }}</td>
            </tr>
            @if ($ticketDetail['ticket']->status === 'closed')
              <tr>
                <th>{{ $t('closed_label') }}</th>
                <td>{{ $ticketDetail['closedBy'] }}</td>
              </tr>
            @endif
          </table>
        </div>
      </div>

      <div class="box">
        <div class="box-header with-border"><h3 class="box-title">{{ $t('set_status_heading') }}</h3></div>
        <form method="POST" action="{{ $root }}">
          {{ csrf_field() }}
          <input type="hidden" name="action" value="tickets.status">
          <input type="hidden" name="ticket" value="{{ $ticketDetail['ticket']->id }}">
          <div class="box-body">
            <div class="form-group">
              <label for="techtyl-ticket-status">{{ $t('field_status') }}</label>
              <select class="form-control" id="techtyl-ticket-status" name="status">
                @foreach ($ticketStatuses as $value => $label)
                  <option value="{{ $value }}" @if ($ticketDetail['ticket']->status === $value) selected @endif>{{ $label }}</option>
                @endforeach
              </select>
              <p class="text-muted small">{{ $t('status_hint') }}</p>
            </div>
          </div>
          <div class="box-footer">
            <button type="submit" class="btn btn-default pull-right">{{ $t('save_status') }}</button>
          </div>
        </form>
        @if ($ticketDetail['ticket']->status !== 'closed')
          <form method="POST" action="{{ $root }}">
            {{ csrf_field() }}
            <input type="hidden" name="action" value="tickets.status">
            <input type="hidden" name="ticket" value="{{ $ticketDetail['ticket']->id }}">
            <input type="hidden" name="status" value="closed">
            <div class="box-footer">
              <button type="submit" class="btn btn-danger btn-sm">{{ $t('close_ticket') }}</button>
            </div>
          </form>
        @endif
      </div>
    </div>
  </div>
@else
  @if (!empty($ticketDetailRequested))
    <div class="alert alert-warning">{{ $t('missing_ticket') }}</div>
  @endif

  <div class="row">
    <div class="col-md-8">
      <div class="box">
        <div class="box-header with-border">
          <h3 class="box-title">{{ $t('title') }}</h3>
          <div class="box-tools">
            <span class="label label-danger">{{ $t('count_open', ['count' => $ticketCounts['open']]) }}</span>
            <span class="label label-warning">{{ $t('count_with_user', ['count' => $ticketCounts['answered'] + $ticketCounts['waiting']]) }}</span>
            <span class="label label-default">{{ $t('count_closed', ['count' => $ticketCounts['closed']]) }}</span>
          </div>
        </div>
        <div class="box-body">
          <form method="GET" action="{{ $root }}" class="form-inline">
            <input type="hidden" name="tab" value="tickets">
            <div class="form-group">
              <label for="techtyl-ticket-filter-status">{{ $t('field_status') }}</label>
              <select class="form-control" id="techtyl-ticket-filter-status" name="status">
                @foreach ($ticketFilters as $value => $label)
                  <option value="{{ $value }}" @if ($ticketStatus === $value) selected @endif>{{ $label }}</option>
                @endforeach
              </select>
            </div>
            <div class="form-group">
              <label for="techtyl-ticket-filter-category">{{ $t('field_category') }}</label>
              <select class="form-control" id="techtyl-ticket-filter-category" name="category">
                <option value="">{{ $t('filter_all') }}</option>
                @foreach ($ticketCategories as $category)
                  <option value="{{ $category->id }}" @if ($ticketCategory === (int) $category->id) selected @endif>{{ $category->name }}</option>
                @endforeach
              </select>
            </div>
            <button type="submit" class="btn btn-default">{{ $t('filter_submit') }}</button>
          </form>
        </div>
        @if (count($ticketList) > 0)
          <div class="box-body table-responsive no-padding">
            <table class="table table-hover">
              <thead>
                <tr>
                  <th>{{ $t('col_number') }}</th>
                  <th>{{ $t('col_subject') }}</th>
                  <th>{{ $t('field_user') }}</th>
                  <th>{{ $t('field_category') }}</th>
                  <th>{{ $t('field_status') }}</th>
                  <th>{{ $t('last_message') }}</th>
                </tr>
              </thead>
              <tbody>
                @foreach ($ticketList as $ticket)
                  <tr>
                    <td>#{{ $ticket->id }}</td>
                    <td>
                      <a href="{{ $root }}?tab=tickets&amp;ticket={{ $ticket->id }}">@if ($ticket->team_unread > 0)<strong>{{ $ticket->subject }}</strong>@else{{ $ticket->subject }}@endif</a>
                      @if ($ticket->team_unread > 0)
                        <span class="badge" title="{{ $t('unread_title') }}">{{ $ticket->team_unread }}</span>
                      @endif
                      @if ($ticket->server !== null)
                        <br><span class="text-muted small">{{ $ticket->server }}</span>
                      @endif
                    </td>
                    <td>{{ $ticket->username }}</td>
                    <td>{{ $ticket->category ?? $t('none_category') }}</td>
                    <td><span class="label {{ $ticketLabels[$ticket->status] ?? 'label-default' }}">{{ $ticketStatuses[$ticket->status] ?? $ticket->status }}</span></td>
                    <td>{{ $ticket->at }}</td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
          @if ($ticketPages > 1)
            <div class="box-footer">
              <span class="text-muted small">{{ $t('page_status', ['page' => $ticketPage, 'pages' => $ticketPages, 'total' => $ticketTotal]) }}</span>
              <div class="pull-right">
                @if ($ticketPage > 1)
                  <a class="btn btn-default btn-sm" href="{{ $root }}?tab=tickets{{ $ticketFilterQuery }}&amp;page={{ $ticketPage - 1 }}">{{ $t('page_back') }}</a>
                @endif
                @if ($ticketPage < $ticketPages)
                  <a class="btn btn-default btn-sm" href="{{ $root }}?tab=tickets{{ $ticketFilterQuery }}&amp;page={{ $ticketPage + 1 }}">{{ $t('page_next') }}</a>
                @endif
              </div>
            </div>
          @endif
        @else
          <div class="box-body">
            <p class="text-muted">
              @if ($ticketStatus === 'active' && $ticketCategory === 0)
                {{ $t('empty_open') }}
              @else
                {{ $t('empty_filter') }}
              @endif
            </p>
          </div>
        @endif
      </div>
    </div>

    <div class="col-md-4">
      <div class="box">
        <div class="box-header with-border"><h3 class="box-title">{{ $t('settings_heading') }}</h3></div>
        <form method="POST" action="{{ $root }}">
          {{ csrf_field() }}
          <input type="hidden" name="action" value="tickets.settings">
          <div class="box-body">
            <div class="form-group">
              <input type="hidden" name="notify" value="0">
              <label><input type="checkbox" name="notify" value="1" @if ($ticketSettings['notify']) checked @endif> {{ $t('notify_label') }}</label>
              <p class="text-muted small">{{ $t('notify_help') }}</p>
            </div>
            <div class="form-group">
              <input type="hidden" name="attachments" value="0">
              <label><input type="checkbox" name="attachments" value="1" @if ($ticketSettings['attachments']) checked @endif> {{ $t('attachments_label') }}</label>
              <p class="text-muted small">{{ $t('attachments_help') }}</p>
            </div>
            <div class="form-group">
              <label for="techtyl-ticket-autoclose">{{ $t('autoclose_label') }}</label>
              <input class="form-control" type="number" min="0" max="{{ $ticketSettingLimits['days'] }}" id="techtyl-ticket-autoclose" name="autoclose_days" value="{{ $ticketSettings['autoclose_days'] }}" required>
              <p class="text-muted small">{{ $t('autoclose_help') }}</p>
            </div>
            <div class="form-group">
              <label for="techtyl-ticket-max-open">{{ $t('max_open_label', ['max' => $ticketSettingLimits['open']]) }}</label>
              <input class="form-control" type="number" min="1" max="{{ $ticketSettingLimits['open'] }}" id="techtyl-ticket-max-open" name="max_open" value="{{ $ticketSettings['max_open'] }}" required>
            </div>
          </div>
          <div class="box-footer">
            <button type="submit" class="btn btn-primary pull-right">{{ $t('save_settings') }}</button>
          </div>
        </form>
      </div>

      <div class="box">
        <div class="box-header with-border"><h3 class="box-title">{{ $t('categories_heading') }}</h3></div>
        <div class="box-body table-responsive no-padding">
          <table class="table">
            <tbody>
              @foreach ($ticketCategories as $category)
                <tr>
                  <td>
                    <form method="POST" action="{{ $root }}">
                      {{ csrf_field() }}
                      <input type="hidden" name="action" value="tickets.category-rename">
                      <input type="hidden" name="category" value="{{ $category->id }}">
                      <div class="input-group input-group-sm">
                        <input class="form-control" type="text" name="name" value="{{ $category->name }}" minlength="2" maxlength="60" required aria-label="{{ $t('category_name_label', ['category' => $category->name]) }}">
                        <span class="input-group-btn">
                          <button type="submit" class="btn btn-default" title="{{ $t('rename') }}"><i class="fa fa-check"></i><span class="sr-only">{{ $t('rename') }}</span></button>
                        </span>
                      </div>
                    </form>
                    <span class="text-muted small">{{ $t((int) $category->tickets === 1 ? 'category_ticket_one' : 'category_tickets', ['count' => $category->tickets]) }}</span>
                  </td>
                  <td class="text-right text-nowrap">
                    <form method="POST" action="{{ $root }}">
                      {{ csrf_field() }}
                      <input type="hidden" name="action" value="tickets.category-move">
                      <input type="hidden" name="category" value="{{ $category->id }}">
                      <button type="submit" name="direction" value="up" class="btn btn-default btn-xs" title="{{ $t('move_up') }}" @if ($loop->first) disabled @endif><i class="fa fa-arrow-up"></i><span class="sr-only">{{ $t('move_up') }}</span></button>
                      <button type="submit" name="direction" value="down" class="btn btn-default btn-xs" title="{{ $t('move_down') }}" @if ($loop->last) disabled @endif><i class="fa fa-arrow-down"></i><span class="sr-only">{{ $t('move_down') }}</span></button>
                    </form>
                  </td>
                  <td class="text-right">
                    <form method="POST" action="{{ $root }}">
                      {{ csrf_field() }}
                      <input type="hidden" name="action" value="tickets.category-delete">
                      <input type="hidden" name="category" value="{{ $category->id }}">
                      <button type="submit" class="btn btn-danger btn-xs" title="{{ (int) $category->tickets > 0 ? $t('category_in_use') : $t('delete') }}" @if ((int) $category->tickets > 0) disabled @endif><i class="fa fa-trash-o"></i><span class="sr-only">{{ $t('delete') }}</span></button>
                    </form>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
        <form method="POST" action="{{ $root }}">
          {{ csrf_field() }}
          <input type="hidden" name="action" value="tickets.category-add">
          <div class="box-footer">
            <div class="input-group input-group-sm">
              <input class="form-control" type="text" name="name" minlength="2" maxlength="60" placeholder="{{ $t('new_category_placeholder') }}" required aria-label="{{ $t('new_category_name') }}">
              <span class="input-group-btn">
                <button type="submit" class="btn btn-primary">{{ $t('category_add') }}</button>
              </span>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
@endif
