<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $certificate->title }}</title>
    <style>
        :root {
            color-scheme: light;
            --ink: #102036;
            --muted: #5b6b82;
            --panel: #ffffff;
            --line: #d9e2ec;
            --accent: #0f766e;
            --accent-soft: #dff7f1;
            --gold: #c28b22;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 32px;
            font-family: Georgia, "Times New Roman", serif;
            background: #f4f7fb;
            color: var(--ink);
        }

        .certificate {
            max-width: 1100px;
            margin: 0 auto;
            background: var(--panel);
            border: 12px solid #17345b;
            box-shadow: 0 18px 60px rgba(16, 32, 54, 0.12);
        }

        .inner {
            border: 2px solid var(--gold);
            margin: 18px;
            padding: 56px 64px 44px;
            position: relative;
        }

        .brand {
            text-align: center;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            font-size: 13px;
            color: var(--muted);
            margin-bottom: 18px;
        }

        .title {
            text-align: center;
            font-size: 44px;
            line-height: 1.1;
            margin: 0;
        }

        .subtitle {
            text-align: center;
            font-size: 15px;
            color: var(--muted);
            margin: 12px 0 32px;
        }

        .recipient-label,
        .completion-label {
            text-align: center;
            color: var(--muted);
            font-size: 15px;
            margin: 0;
        }

        .recipient {
            text-align: center;
            font-size: 38px;
            line-height: 1.2;
            margin: 12px 0 24px;
            color: var(--accent);
        }

        .program {
            text-align: center;
            font-size: 26px;
            margin: 14px 0 8px;
        }

        .summary {
            max-width: 760px;
            margin: 18px auto 0;
            padding: 16px 18px;
            background: var(--accent-soft);
            border: 1px solid #bfe8dd;
            border-radius: 12px;
            text-align: center;
            font-size: 15px;
            color: #214049;
        }

        .skills {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 8px;
            margin: 24px 0 0;
            padding: 0;
            list-style: none;
        }

        .skills li {
            padding: 7px 12px;
            border-radius: 999px;
            background: #eef7ff;
            border: 1px solid #cfe0f4;
            color: #204465;
            font-size: 13px;
        }

        .footer {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 18px;
            margin-top: 42px;
            padding-top: 24px;
            border-top: 1px solid var(--line);
        }

        .meta-card {
            padding: 14px 16px;
            border: 1px solid var(--line);
            border-radius: 12px;
            background: #fbfdff;
        }

        .meta-label {
            font-size: 11px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--muted);
            margin-bottom: 6px;
        }

        .meta-value {
            font-size: 16px;
            line-height: 1.4;
        }

        .verify {
            margin-top: 28px;
            text-align: center;
            font-size: 13px;
            color: var(--muted);
        }

        .verify strong {
            color: var(--ink);
        }

        .verify a {
            color: var(--accent);
            text-decoration: none;
            word-break: break-word;
        }

        @media print {
            body {
                padding: 0;
                background: #ffffff;
            }

            .certificate {
                box-shadow: none;
                max-width: none;
            }
        }
    </style>
</head>
<body>
    @php
        $programTitle = $certificate->certifiable->title ?? $certificate->certifiable->name ?? $certificate->title;
        $fullName = trim(($certificate->user->first_name ?? '') . ' ' . ($certificate->user->last_name ?? ''));
        $skills = data_get($certificate, 'metadata.skills', []);
        $summaryParts = array_filter([
            data_get($certificate, 'metadata.score') ? 'Score: ' . data_get($certificate, 'metadata.score') : null,
            data_get($certificate, 'metadata.duration') ? 'Duration: ' . data_get($certificate, 'metadata.duration') : null,
        ]);
    @endphp

    <main class="certificate">
        <section class="inner">
            <p class="brand">JK Michaels Professional Mastery</p>
            <h1 class="title">Certificate of Completion</h1>
            <p class="subtitle">Issued in recognition of successful course completion and demonstrated progress.</p>

            <p class="recipient-label">This certifies that</p>
            <h2 class="recipient">{{ $fullName ?: 'Learner' }}</h2>

            <p class="completion-label">has successfully completed</p>
            <p class="program">{{ $programTitle }}</p>

            @if (! empty($summaryParts))
                <div class="summary">
                    {{ implode(' | ', $summaryParts) }}
                </div>
            @endif

            @if (! empty($skills))
                <ul class="skills">
                    @foreach ($skills as $skill)
                        <li>{{ $skill }}</li>
                    @endforeach
                </ul>
            @endif

            <div class="footer">
                <div class="meta-card">
                    <div class="meta-label">Certificate Number</div>
                    <div class="meta-value">{{ $certificate->certificate_number }}</div>
                </div>
                <div class="meta-card">
                    <div class="meta-label">Issued On</div>
                    <div class="meta-value">{{ optional($certificate->issued_at)->format('F j, Y') }}</div>
                </div>
                <div class="meta-card">
                    <div class="meta-label">Verification</div>
                    <div class="meta-value">Public verification available</div>
                </div>
            </div>

            <p class="verify">
                <strong>Verify this certificate:</strong>
                <a href="{{ $verifyUrl }}">{{ $verifyUrl }}</a>
            </p>
        </section>
    </main>
</body>
</html>
