{{--
  A printable invoice.

  Standalone rather than inside the panel: this is meant to go to a printer or
  a PDF, so it carries no menu, no navbar and no colours that cost ink. The
  browser's own print dialog does the PDF, which keeps one layout instead of a
  screen version and a separate library-rendered one.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>{{ $invoice->number }} — {{ $invoice->dispatcher?->name }}</title>

  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;500;600;700&display=swap" rel="stylesheet" />

  <style>
    :root { --ink: #2f2b3d; --muted: #6f6b7d; --line: #dbdade; --accent: #7367f0; }

    * { box-sizing: border-box; }

    body {
      margin: 0; padding: 2rem 1rem;
      background: #f4f4f6; color: var(--ink);
      font-family: 'Public Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      font-size: 14px; line-height: 1.5;
    }

    .sheet {
      max-width: 48rem; margin: 0 auto; padding: 2.5rem;
      background: #fff; border: 1px solid var(--line); border-radius: .5rem;
    }

    .top { display: flex; flex-wrap: wrap; gap: 1.5rem; justify-content: space-between; margin-bottom: 2rem; }
    .company { font-size: 1.125rem; font-weight: 700; }
    .muted { color: var(--muted); }
    .right { text-align: right; }

    h1 { font-size: 1.5rem; margin: 0 0 .25rem; }

    .meta { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
    .meta h2 { font-size: .75rem; text-transform: uppercase; letter-spacing: .04em; color: var(--muted); margin: 0 0 .375rem; }

    table { width: 100%; border-collapse: collapse; margin-bottom: 1.5rem; }
    th, td { padding: .625rem .5rem; text-align: left; border-bottom: 1px solid var(--line); }
    th { font-size: .75rem; text-transform: uppercase; letter-spacing: .04em; color: var(--muted); }
    td.amount, th.amount { text-align: right; font-variant-numeric: tabular-nums; }
    tfoot td { border-bottom: 0; font-weight: 700; font-size: 1.0625rem; padding-top: 1rem; }

    .status {
      display: inline-block; padding: .25rem .625rem; border-radius: 999px;
      font-size: .75rem; font-weight: 600; border: 1px solid var(--line);
    }

    .notes { border-top: 1px solid var(--line); padding-top: 1rem; color: var(--muted); font-size: .8125rem; }

    .actions { max-width: 48rem; margin: 1rem auto 0; display: flex; gap: .5rem; justify-content: flex-end; }
    .btn {
      padding: .5rem 1rem; border: 1px solid var(--line); border-radius: .375rem;
      background: #fff; color: var(--ink); font: inherit; font-weight: 600;
      text-decoration: none; cursor: pointer;
    }
    .btn.primary { background: var(--accent); border-color: var(--accent); color: #fff; }

    @media print {
      body { background: #fff; padding: 0; }
      .sheet { border: 0; border-radius: 0; padding: 0; max-width: none; }
      .actions { display: none; }
    }

    @media (max-width: 575.98px) {
      body { padding: 1rem .75rem; }
      .sheet { padding: 1.25rem; }
      .meta { grid-template-columns: 1fr; gap: 1rem; }
    }
  </style>
</head>
<body>
  <div class="sheet">

    <div class="top">
      <div>
        <div class="company">{{ $invoice->dispatcher?->name }}</div>
        @if($invoice->dispatcher?->phone_number)
          <div class="muted">{{ $invoice->dispatcher->phone_number }}</div>
        @endif
        <div class="muted">{{ $invoice->dispatcher?->email }}</div>
      </div>

      <div class="right">
        <h1>{{ $invoice->number }}</h1>
        <div class="status">{{ $invoice->statusLabel() }}</div>
      </div>
    </div>

    <div class="meta">
      <div>
        <h2>Bill to</h2>
        <div><strong>{{ $invoice->payer_name }}</strong></div>
        <div class="muted">{{ $invoice->payerTypeLabel() }}</div>
        @if($invoice->payer_reference)
          <div class="muted">Ref: {{ $invoice->payer_reference }}</div>
        @endif
      </div>

      <div>
        <h2>Dates</h2>
        <div>Issued: {{ $invoice->issued_on->format('d M Y') }}</div>
        @if($invoice->due_on)
          <div>Due: {{ $invoice->due_on->format('d M Y') }}</div>
        @endif
        @if($invoice->paid_at)
          <div>Paid: {{ $invoice->paid_at->format('d M Y') }}</div>
        @endif
      </div>
    </div>

    <table>
      <thead>
        <tr>
          <th>Description</th>
          <th class="amount">Amount</th>
        </tr>
      </thead>
      <tbody>
        @foreach($invoice->items as $item)
        <tr>
          <td>{{ $item->description }}</td>
          <td class="amount">${{ number_format((float) $item->amount, 2) }}</td>
        </tr>
        @endforeach
      </tbody>
      <tfoot>
        <tr>
          <td>Total</td>
          <td class="amount">${{ number_format((float) $invoice->amount, 2) }}</td>
        </tr>
      </tfoot>
    </table>

    @if($invoice->notes)
      <div class="notes">{{ $invoice->notes }}</div>
    @endif
  </div>

  <div class="actions">
    <a class="btn" href="{{ route('dispatcher.billing.show', $invoice->id) }}">Back</a>
    <button class="btn primary" type="button" onclick="window.print()">Print</button>
  </div>
</body>
</html>
