<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 2cm 1.8cm; }
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10.5px;
            color: #1a1d21;
            line-height: 1.6;
        }
        .cover {
            text-align: center;
            padding: 1.5cm 0 1cm;
            border-bottom: 2px solid #6366f1;
            margin-bottom: 1.2cm;
        }
        .brand {
            font-size: 9px;
            letter-spacing: 3px;
            color: #6366f1;
            font-weight: 700;
            margin-bottom: 6px;
        }
        h1.title {
            font-size: 20px;
            margin: 0 0 8px;
            line-height: 1.3;
        }
        .meta {
            font-size: 9px;
            color: #666;
        }
        .summary {
            background: #f4f4fb;
            border-left: 3px solid #6366f1;
            padding: 10px 14px;
            margin: 0 0 1cm;
            font-size: 10px;
            color: #333;
        }
        h2.sec-title {
            font-size: 14px;
            color: #312e81;
            border-bottom: 1px solid #ddd;
            padding-bottom: 4px;
            margin-top: 0.9cm;
            page-break-after: avoid;
        }
        .sec-body { margin-top: 6px; }
        .sec-body h2, .sec-body h3, .sec-body h4 {
            font-size: 11.5px;
            margin: 10px 0 4px;
            color: #1f2937;
            page-break-after: avoid;
        }
        .sec-body p { margin: 4px 0; }
        .sec-body ul { margin: 4px 0 4px 18px; padding: 0; }
        .sec-body li { margin: 2px 0; }
        .sec-body strong { color: #111; }
        .sec-body pre {
            background: #f5f5f5;
            border: 1px solid #e0e0e0;
            padding: 8px;
            font-family: DejaVu Sans Mono, monospace;
            font-size: 8.5px;
            white-space: pre-wrap;
        }
        .sec-body code {
            font-family: DejaVu Sans Mono, monospace;
            font-size: 9px;
            background: #f0f0f0;
            padding: 0 3px;
        }
        .footer {
            position: fixed;
            bottom: -1.6cm;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 8px;
            color: #999;
        }
    </style>
</head>
<body>
    <div class="cover">
        <div class="brand">PRDFORGE</div>
        <h1 class="title">{{ $title }}</h1>
        <div class="meta">Product Requirements Document · Versi {{ $versionLabel }} · {{ $exportedAt }}</div>
    </div>

    @if($summary)
        <div class="summary">{{ $summary }}</div>
    @endif

    {!! $sectionsHtml !!}

    <div class="footer">Diekspor dari PRDForge — AI Product Discovery &amp; PRD Studio</div>
</body>
</html>
