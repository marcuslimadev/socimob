<!DOCTYPE html>
<html>
<head>
    <title>Recuperação de Senha</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">
        <h2 style="color: #4a5568; text-align: center;">Recuperação de Senha</h2>
        <p>Olá,</p>
        <p>Recebemos uma solicitação para redefinir a senha da sua conta no SOCIMOB.</p>
        <p>Se você não solicitou isso, pode ignorar este e-mail com segurança.</p>
        <p>Para redefinir sua senha, clique no botão abaixo:</p>
        <div style="text-align: center; margin: 30px 0;">
            <a href="{{ env('APP_URL') }}/reset-password?token={{ $token }}&email={{ urlencode($email) }}" 
               style="background-color: #3182ce; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; font-weight: bold;">
                Redefinir Senha
            </a>
        </div>
        <p>Este link expira em 60 minutos.</p>
        <p style="font-size: 0.9em; color: #718096; margin-top: 30px; border-top: 1px solid #eee; padding-top: 10px;">
            Se você estiver com problemas para clicar no botão "Redefinir Senha", copie e cole o URL abaixo no seu navegador: <br>
            <a href="{{ env('APP_URL') }}/reset-password?token={{ $token }}&email={{ urlencode($email) }}" style="color: #3182ce;">
                {{ env('APP_URL') }}/reset-password?token={{ $token }}&email={{ urlencode($email) }}
            </a>
        </p>
    </div>
</body>
</html>
