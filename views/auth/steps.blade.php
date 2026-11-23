<ol class="ta-steps" aria-label="{{ $t('steps_label') }}">
  @foreach (['step_details', 'step_confirm', 'step_password'] as $index => $label)
    <li class="ta-step @if ($index + 1 < $step) is-done @elseif ($index + 1 === $step) is-now @endif" @if ($index + 1 === $step) aria-current="step" @endif>
      <span class="ta-step-no" aria-hidden="true">{{ $index + 1 }}</span>
      <span class="ta-step-label">{{ $t($label) }}</span>
    </li>
  @endforeach
</ol>
