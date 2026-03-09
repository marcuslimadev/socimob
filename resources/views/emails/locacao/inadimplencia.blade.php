<!DOCTYPE html>
<html>
<head><title>Aviso de Inadimplência</title></head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f4f4f4;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <div style="background-color: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <div style="background: linear-gradient(135deg, #e53e3e 0%, #c53030 100%); padding: 30px; text-align: center;">
                <h1 style="color: #fff; margin: 0; font-size: 22px;">Aviso de Inadimplência</h1>
            </div>
            <div style="padding: 30px;">
                <p>Olá, <strong>{{ $cobranca->contrato->locatario->nome ?? 'Inquilino' }}</strong>!</p>
                <p>Identificamos que seu aluguel referente à competência <strong>{{ $cobranca->competencia }}</strong> encontra-se em atraso.</p>
                <div style="background-color: #fff5f5; border-radius: 8px; padding: 20px; margin: 20px 0; border-left: 4px solid #e53e3e;">
                    <p style="margin: 0;"><strong>Valor:</strong> R$ {{ number_format($cobranca->valor_total, 2, ',', '.') }}</p>
                    <p style="margin: 8px 0 0;"><strong>Vencimento:</strong> {{ \Carbon\Carbon::parse($cobranca->data_vencimento)->format('d/m/Y') }}</p>
                    <p style="margin: 8px 0 0;"><strong>Dias em atraso:</strong> {{ $diasAtraso }}</p>
                    <p style="margin: 8px 0 0;"><strong>Contrato:</strong> #{{ $cobranca->contrato->numero_contrato ?? $cobranca->contrato_id }}</p>
                </div>
                <p>Por favor, regularize sua situação o mais breve possível para evitar encargos adicionais.</p>
                <p>Em caso de dúvidas, entre em contato conosco imediatamente.</p>
            </div>
            <div style="background-color: #f7fafc; padding: 20px; text-align: center; border-top: 1px solid #e2e8f0;">
                <p style="margin: 0; color: #718096; font-size: 12px;">Notificação automática — SOCIMOB</p>
            </div>
        </div>
    </div>
</body>
</html>
