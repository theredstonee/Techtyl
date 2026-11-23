@php
  $ticketLabels = ['open' => 'label-danger', 'answered' => 'label-success', 'waiting' => 'label-warning', 'closed' => 'label-default'];
@endphp

@if (empty($ticketsReady))
  <div class="alert alert-warning">
    Die Tabellen der Tickets fehlen noch. Bitte einmal <code>php artisan migrate --force</code> im Panel-Ordner ausfuehren.
  </div>
@elseif ($ticketDetail !== null)
  {{-- Detailansicht: ?tab=tickets&ticket=<id> --}}
  <p><a href="{{ $root }}?tab=tickets" class="btn btn-default btn-sm"><i class="fa fa-angle-left"></i> Alle Tickets</a></p>

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
                    <span class="label label-primary">Team</span>
                  @else
                    <span class="label label-default">Nutzer</span>
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
                    <img src="{{ $attachment['url'] }}" alt="Anhang, {{ $attachment['label'] }}" class="img-thumbnail" width="220" loading="lazy">
                  </a>
                @endforeach
              </div>
            @endif
          @endforeach
        </div>
      </div>

      <div class="box">
        <div class="box-header with-border"><h3 class="box-title">Antworten</h3></div>
        <form method="POST" action="{{ $root }}">
          {{ csrf_field() }}
          <input type="hidden" name="action" value="tickets.reply">
          <input type="hidden" name="ticket" value="{{ $ticketDetail['ticket']->id }}">
          <div class="box-body">
            <div class="form-group">
              <label for="techtyl-ticket-body">Antwort an {{ $ticketDetail['ticket']->username }}</label>
              <textarea class="form-control" id="techtyl-ticket-body" name="body" rows="7" maxlength="5000" required>{{ old('body') }}</textarea>
              <p class="text-muted small">Wird als reiner Text angezeigt, Zeilenumbrueche bleiben erhalten. Der Nutzer sieht deinen Vornamen.</p>
            </div>
            <div class="form-group">
              <label for="techtyl-ticket-then">Status danach</label>
              <select class="form-control" id="techtyl-ticket-then" name="then">
                <option value="answered">Beantwortet</option>
                <option value="waiting">Wartet auf Nutzer (Rueckfrage)</option>
                <option value="closed">Geschlossen</option>
              </select>
            </div>
          </div>
          <div class="box-footer">
            <button type="submit" class="btn btn-primary pull-right">Antwort senden</button>
          </div>
        </form>
      </div>
    </div>

    <div class="col-md-4">
      <div class="box">
        <div class="box-header with-border"><h3 class="box-title">Angaben</h3></div>
        <div class="box-body table-responsive no-padding">
          <table class="table">
            <tr>
              <th>Nutzer</th>
              <td><a href="/admin/users/view/{{ $ticketDetail['ticket']->user_id }}">{{ $ticketDetail['ticket']->username }}</a><br><span class="text-muted small">{{ $ticketDetail['ticket']->email }}</span></td>
            </tr>
            <tr>
              <th>Kategorie</th>
              <td>{{ $ticketDetail['ticket']->category ?? 'Ohne' }}</td>
            </tr>
            <tr>
              <th>Server</th>
              <td>
                @if ($ticketDetail['ticket']->server_id !== null)
                  <a href="/admin/servers/view/{{ $ticketDetail['ticket']->server_id }}">{{ $ticketDetail['ticket']->server }}</a>
                @else
                  <span class="text-muted">Keiner</span>
                @endif
              </td>
            </tr>
            <tr>
              <th>Eroeffnet</th>
              <td>{{ $ticketDetail['created'] }}</td>
            </tr>
            <tr>
              <th>Letzte Nachricht</th>
              <td>{{ $ticketDetail['updated'] }}</td>
            </tr>
            @if ($ticketDetail['ticket']->status === 'closed')
              <tr>
                <th>Geschlossen</th>
                <td>{{ $ticketDetail['closedBy'] }}</td>
              </tr>
            @endif
          </table>
        </div>
      </div>

      <div class="box">
        <div class="box-header with-border"><h3 class="box-title">Status setzen</h3></div>
        <form method="POST" action="{{ $root }}">
          {{ csrf_field() }}
          <input type="hidden" name="action" value="tickets.status">
          <input type="hidden" name="ticket" value="{{ $ticketDetail['ticket']->id }}">
          <div class="box-body">
            <div class="form-group">
              <label for="techtyl-ticket-status">Status</label>
              <select class="form-control" id="techtyl-ticket-status" name="status">
                @foreach ($ticketStatuses as $value => $label)
                  <option value="{{ $value }}" @if ($ticketDetail['ticket']->status === $value) selected @endif>{{ $label }}</option>
                @endforeach
              </select>
              <p class="text-muted small">Der Wechsel erscheint im Verlauf. Ohne Antwort wird keine E-Mail verschickt.</p>
            </div>
          </div>
          <div class="box-footer">
            <button type="submit" class="btn btn-default pull-right">Status speichern</button>
          </div>
        </form>
        @if ($ticketDetail['ticket']->status !== 'closed')
          <form method="POST" action="{{ $root }}">
            {{ csrf_field() }}
            <input type="hidden" name="action" value="tickets.status">
            <input type="hidden" name="ticket" value="{{ $ticketDetail['ticket']->id }}">
            <input type="hidden" name="status" value="closed">
            <div class="box-footer">
              <button type="submit" class="btn btn-danger btn-sm">Ticket schliessen</button>
            </div>
          </form>
        @endif
      </div>
    </div>
  </div>
@else
  @if (!empty($ticketDetailRequested))
    <div class="alert alert-warning">Dieses Ticket gibt es nicht (mehr).</div>
  @endif

  <div class="row">
    <div class="col-md-8">
      <div class="box">
        <div class="box-header with-border">
          <h3 class="box-title">Tickets</h3>
          <div class="box-tools">
            <span class="label label-danger">{{ $ticketCounts['open'] }} offen</span>
            <span class="label label-warning">{{ $ticketCounts['answered'] + $ticketCounts['waiting'] }} beim Nutzer</span>
            <span class="label label-default">{{ $ticketCounts['closed'] }} geschlossen</span>
          </div>
        </div>
        <div class="box-body">
          <form method="GET" action="{{ $root }}" class="form-inline">
            <input type="hidden" name="tab" value="tickets">
            <div class="form-group">
              <label for="techtyl-ticket-filter-status">Status</label>
              <select class="form-control" id="techtyl-ticket-filter-status" name="status">
                @foreach ($ticketFilters as $value => $label)
                  <option value="{{ $value }}" @if ($ticketStatus === $value) selected @endif>{{ $label }}</option>
                @endforeach
              </select>
            </div>
            <div class="form-group">
              <label for="techtyl-ticket-filter-category">Kategorie</label>
              <select class="form-control" id="techtyl-ticket-filter-category" name="category">
                <option value="">Alle</option>
                @foreach ($ticketCategories as $category)
                  <option value="{{ $category->id }}" @if ($ticketCategory === (int) $category->id) selected @endif>{{ $category->name }}</option>
                @endforeach
              </select>
            </div>
            <button type="submit" class="btn btn-default">Filtern</button>
          </form>
        </div>
        @if (count($ticketList) > 0)
          <div class="box-body table-responsive no-padding">
            <table class="table table-hover">
              <thead>
                <tr>
                  <th>Nr.</th>
                  <th>Betreff</th>
                  <th>Nutzer</th>
                  <th>Kategorie</th>
                  <th>Status</th>
                  <th>Letzte Nachricht</th>
                </tr>
              </thead>
              <tbody>
                @foreach ($ticketList as $ticket)
                  <tr>
                    <td>#{{ $ticket->id }}</td>
                    <td>
                      <a href="{{ $root }}?tab=tickets&amp;ticket={{ $ticket->id }}">@if ($ticket->team_unread > 0)<strong>{{ $ticket->subject }}</strong>@else{{ $ticket->subject }}@endif</a>
                      @if ($ticket->team_unread > 0)
                        <span class="badge" title="Ungelesene Nachrichten">{{ $ticket->team_unread }}</span>
                      @endif
                      @if ($ticket->server !== null)
                        <br><span class="text-muted small">{{ $ticket->server }}</span>
                      @endif
                    </td>
                    <td>{{ $ticket->username }}</td>
                    <td>{{ $ticket->category ?? 'Ohne' }}</td>
                    <td><span class="label {{ $ticketLabels[$ticket->status] ?? 'label-default' }}">{{ $ticketStatuses[$ticket->status] ?? $ticket->status }}</span></td>
                    <td>{{ $ticket->at }}</td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
          @if ($ticketPages > 1)
            <div class="box-footer">
              <span class="text-muted small">Seite {{ $ticketPage }} von {{ $ticketPages }}, {{ $ticketTotal }} Tickets</span>
              <div class="pull-right">
                @if ($ticketPage > 1)
                  <a class="btn btn-default btn-sm" href="{{ $root }}?tab=tickets{{ $ticketFilterQuery }}&amp;page={{ $ticketPage - 1 }}">Zurueck</a>
                @endif
                @if ($ticketPage < $ticketPages)
                  <a class="btn btn-default btn-sm" href="{{ $root }}?tab=tickets{{ $ticketFilterQuery }}&amp;page={{ $ticketPage + 1 }}">Weiter</a>
                @endif
              </div>
            </div>
          @endif
        @else
          <div class="box-body">
            <p class="text-muted">
              @if ($ticketStatus === 'active' && $ticketCategory === 0)
                Keine offenen Tickets. Sobald ein Nutzer schreibt, erscheint das Ticket hier.
              @else
                Zu diesem Filter gibt es keine Tickets.
              @endif
            </p>
          </div>
        @endif
      </div>
    </div>

    <div class="col-md-4">
      <div class="box">
        <div class="box-header with-border"><h3 class="box-title">Einstellungen</h3></div>
        <form method="POST" action="{{ $root }}">
          {{ csrf_field() }}
          <input type="hidden" name="action" value="tickets.settings">
          <div class="box-body">
            <div class="form-group">
              <input type="hidden" name="notify" value="0">
              <label><input type="checkbox" name="notify" value="1" @if ($ticketSettings['notify']) checked @endif> E-Mail bei neuer Antwort</label>
              <p class="text-muted small">Nutzer erhalten einen Hinweis bei einer Antwort des Teams, Root-Admins bei neuen Tickets und Antworten. Die Mail enthaelt nur die Ticketnummer und einen Link, keinen Nachrichtentext.</p>
            </div>
            <div class="form-group">
              <input type="hidden" name="attachments" value="0">
              <label><input type="checkbox" name="attachments" value="1" @if ($ticketSettings['attachments']) checked @endif> Bilder als Anhang erlauben</label>
              <p class="text-muted small">Bis zu 3 Bilder je Nachricht, neu kodiert und nur fuer den Nutzer des Tickets und Root-Admins abrufbar. Die Dateigroesse folgt der Grenze im Reiter Design.</p>
            </div>
            <div class="form-group">
              <label for="techtyl-ticket-autoclose">Automatisch schliessen nach Tagen ohne Antwort des Nutzers</label>
              <input class="form-control" type="number" min="0" max="{{ $ticketSettingLimits['days'] }}" id="techtyl-ticket-autoclose" name="autoclose_days" value="{{ $ticketSettings['autoclose_days'] }}" required>
              <p class="text-muted small">0 schaltet das automatische Schliessen ab. Gezaehlt wird ab der letzten Antwort des Teams; braucht den Cron des Panels.</p>
            </div>
            <div class="form-group">
              <label for="techtyl-ticket-max-open">Offene Tickets je Nutzer (1 bis {{ $ticketSettingLimits['open'] }})</label>
              <input class="form-control" type="number" min="1" max="{{ $ticketSettingLimits['open'] }}" id="techtyl-ticket-max-open" name="max_open" value="{{ $ticketSettings['max_open'] }}" required>
            </div>
          </div>
          <div class="box-footer">
            <button type="submit" class="btn btn-primary pull-right">Einstellungen speichern</button>
          </div>
        </form>
      </div>

      <div class="box">
        <div class="box-header with-border"><h3 class="box-title">Kategorien</h3></div>
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
                        <input class="form-control" type="text" name="name" value="{{ $category->name }}" minlength="2" maxlength="60" required aria-label="Name der Kategorie {{ $category->name }}">
                        <span class="input-group-btn">
                          <button type="submit" class="btn btn-default" title="Umbenennen"><i class="fa fa-check"></i><span class="sr-only">Umbenennen</span></button>
                        </span>
                      </div>
                    </form>
                    <span class="text-muted small">{{ $category->tickets }} {{ (int) $category->tickets === 1 ? 'Ticket' : 'Tickets' }}</span>
                  </td>
                  <td class="text-right text-nowrap">
                    <form method="POST" action="{{ $root }}">
                      {{ csrf_field() }}
                      <input type="hidden" name="action" value="tickets.category-move">
                      <input type="hidden" name="category" value="{{ $category->id }}">
                      <button type="submit" name="direction" value="up" class="btn btn-default btn-xs" title="Nach oben" @if ($loop->first) disabled @endif><i class="fa fa-arrow-up"></i><span class="sr-only">Nach oben</span></button>
                      <button type="submit" name="direction" value="down" class="btn btn-default btn-xs" title="Nach unten" @if ($loop->last) disabled @endif><i class="fa fa-arrow-down"></i><span class="sr-only">Nach unten</span></button>
                    </form>
                  </td>
                  <td class="text-right">
                    <form method="POST" action="{{ $root }}">
                      {{ csrf_field() }}
                      <input type="hidden" name="action" value="tickets.category-delete">
                      <input type="hidden" name="category" value="{{ $category->id }}">
                      <button type="submit" class="btn btn-danger btn-xs" title="@if ((int) $category->tickets > 0) Wird von Tickets verwendet @else Loeschen @endif" @if ((int) $category->tickets > 0) disabled @endif><i class="fa fa-trash-o"></i><span class="sr-only">Loeschen</span></button>
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
              <input class="form-control" type="text" name="name" minlength="2" maxlength="60" placeholder="Neue Kategorie" required aria-label="Name der neuen Kategorie">
              <span class="input-group-btn">
                <button type="submit" class="btn btn-primary">Anlegen</button>
              </span>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
@endif
