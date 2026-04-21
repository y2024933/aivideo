<!DOCTYPE html>
<html lang="zh-TW">
<head><meta charset="UTF-8"></head>
<body style="font-family: sans-serif; color: #333; line-height: 1.6; max-width: 600px; margin: 0 auto; padding: 20px;">
    <h2 style="color: #1a1a1a; border-bottom: 2px solid #e5e5e5; padding-bottom: 10px;">
        📩 新的聯絡訊息
    </h2>

    <table style="width: 100%; border-collapse: collapse;">
        <tr>
            <td style="padding: 8px 12px; font-weight: bold; width: 120px; vertical-align: top;">網站</td>
            <td style="padding: 8px 12px;">{{ $site->name }}</td>
        </tr>
        <tr style="background: #f9f9f9;">
            <td style="padding: 8px 12px; font-weight: bold; vertical-align: top;">詢問類型</td>
            <td style="padding: 8px 12px;">{{ $contactMessage->inquiry_type }}</td>
        </tr>
        <tr>
            <td style="padding: 8px 12px; font-weight: bold; vertical-align: top;">姓名</td>
            <td style="padding: 8px 12px;">{{ $contactMessage->name }}</td>
        </tr>
        <tr style="background: #f9f9f9;">
            <td style="padding: 8px 12px; font-weight: bold; vertical-align: top;">電話</td>
            <td style="padding: 8px 12px;">{{ $contactMessage->phone }}</td>
        </tr>
        @if($contactMessage->email)
        <tr>
            <td style="padding: 8px 12px; font-weight: bold; vertical-align: top;">Email</td>
            <td style="padding: 8px 12px;">{{ $contactMessage->email }}</td>
        </tr>
        @endif
        @if($contactMessage->line_id)
        <tr style="background: #f9f9f9;">
            <td style="padding: 8px 12px; font-weight: bold; vertical-align: top;">LINE ID</td>
            <td style="padding: 8px 12px;">{{ $contactMessage->line_id }}</td>
        </tr>
        @endif
        @if($contactMessage->contact_time)
        <tr>
            <td style="padding: 8px 12px; font-weight: bold; vertical-align: top;">方便時間</td>
            <td style="padding: 8px 12px;">{{ $contactMessage->contact_time }}</td>
        </tr>
        @endif
        @if($contactMessage->project)
        <tr style="background: #f9f9f9;">
            <td style="padding: 8px 12px; font-weight: bold; vertical-align: top;">詢問建案</td>
            <td style="padding: 8px 12px;">{{ $contactMessage->project->name }}</td>
        </tr>
        @endif
        <tr>
            <td style="padding: 8px 12px; font-weight: bold; vertical-align: top;">訊息內容</td>
            <td style="padding: 8px 12px; white-space: pre-wrap;">{{ $contactMessage->message }}</td>
        </tr>
    </table>

    <p style="margin-top: 20px; font-size: 13px; color: #888;">
        來源頁面：{{ $contactMessage->source_page }}<br>
        送出時間：{{ $contactMessage->created_at->format('Y-m-d H:i:s') }}
    </p>
</body>
</html>
