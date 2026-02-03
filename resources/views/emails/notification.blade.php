<!DOCTYPE html>
<html>
<head>
    <title>{{ $title ?? 'Notificação' }}</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f4f4f4;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <div style="background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <!-- Header -->
            <div style="background: linear-gradient(135deg, #3182ce 0%, #2c5282 100%); padding: 30px; text-align: center;">
                <h1 style="color: #ffffff; margin: 0; font-size: 24px;">{{ $title ?? 'Notificação' }}</h1>
            </div>

            <!-- Content -->
            <div style="padding: 30px;">
                <p style="margin-top: 0;">Olá{{ isset($user) && $user->name ? ', ' . $user->name : '' }}!</p>

                <div style="background-color: #f7fafc; border-radius: 8px; padding: 20px; margin: 20px 0; border-left: 4px solid #3182ce;">
                    <p style="margin: 0; color: #4a5568;">
                        {{ $message }}
                    </p>
                </div>

                @if(isset($action_url) && $action_url)
                <div style="text-align: center; margin: 30px 0;">
                    <a href="{{ $action_url }}"
                       style="background-color: #3182ce; color: white; padding: 14px 28px; text-decoration: none; border-radius: 6px; font-weight: bold; display: inline-block;">
                        Ver Detalhes
                    </a>
                </div>
                @endif
            </div>

            <!-- Footer -->
            <div style="background-color: #f7fafc; padding: 20px; text-align: center; border-top: 1px solid #e2e8f0;">
                <p style="margin: 0; color: #718096; font-size: 12px;">
                    Esta é uma notificação automática do SOCIMOB.
                </p>
            </div>
        </div>
    </div>
</body>
</html>
