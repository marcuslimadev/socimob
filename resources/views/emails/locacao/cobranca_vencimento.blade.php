<!DOCTYPE html>
<html>
<head><title>Lembrete de Vencimento</title></head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f4f4f4;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <div style="background-color: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <div style="background: linear-gradient(135deg, #3182ce 0%, #2c5282 100%); padding: 30px; text-align: center;">
                <h1 style="color: #fff; margin: 0; font-size: 22px;">Lembrete de Vencimento de Aluguel</h1>
            </div>
            <div style="padding: 30px;">
                <p>Olá, <strong>{{ $cobranca->contrato->locatario->nome ?? 'Inquilino' }}</strong>!</p>
                <p>Este é um lembrete de que seu aluguel referente à competência <strong>{{ $cobranca->competencia }}</strong> está prestes a vencer.</p>
                <div style="background-color: #f7fafc; border-radius: 8px; padding: 20px; margin: 20px 0; border-left: 4px solid #3182ce;">
                    <p style="margin: 0;"><strong>Valor:</strong> R$ {{ number_format($cobranca->valor_total, 2, ',', '.') }}</p>
                    <p style="margin: 8px 0 0;"><strong>Vencimento:</strong> {{ \Carbon\Carbon::parse($cobranca->data_vencimento)->format('d/m/Y') }}</p>
                    <p style="margin: 8px 0 0;"><strong>Contrato:</strong> #{{ $cobranca->contrato->numero_contrato ?? $cobranca->contrato_id }}</p>
                </div>
                <p>Em caso de dúvidas, entre em contato conosco.</p>
            </div>
            <div style="background-color: #f7fafc; padding: 20px; text-align: center; border-top: 1px solid #e2e8f0;">
                <p style="margin: 0; color: #718096; font-size: 12px;">Notificação automática — SOCIMOB</p>
            </div>
        </div>
    </div>
</body>
</html>
