<!DOCTYPE html>
<html>
<head><title>Aviso de Expiração de Contrato</title></head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f4f4f4;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <div style="background-color: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <div style="background: linear-gradient(135deg, #d69e2e 0%, #b7791f 100%); padding: 30px; text-align: center;">
                <h1 style="color: #fff; margin: 0; font-size: 22px;">Aviso de Expiração de Contrato</h1>
            </div>
            <div style="padding: 30px;">
                <p>Prezado(a),</p>
                <p>Informamos que o contrato de locação abaixo expirará em <strong>{{ $diasRestantes }} dias</strong>.</p>
                <div style="background-color: #fffff0; border-radius: 8px; padding: 20px; margin: 20px 0; border-left: 4px solid #d69e2e;">
                    <p style="margin: 0;"><strong>Contrato:</strong> #{{ $contrato->numero_contrato ?? $contrato->id }}</p>
                    @if($contrato->imovel)
                    <p style="margin: 8px 0 0;"><strong>Imóvel:</strong> {{ $contrato->imovel->titulo ?? '' }}</p>
                    @endif
                    <p style="margin: 8px 0 0;"><strong>Data de Término:</strong> {{ $contrato->fim?->format('d/m/Y') }}</p>
                </div>
                <p>Por favor, entre em contato conosco para tratar da renovação ou encerramento do contrato.</p>
            </div>
            <div style="background-color: #f7fafc; padding: 20px; text-align: center; border-top: 1px solid #e2e8f0;">
                <p style="margin: 0; color: #718096; font-size: 12px;">Notificação automática — SOCIMOB</p>
            </div>
        </div>
    </div>
</body>
</html>
