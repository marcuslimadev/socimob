<!DOCTYPE html>
<html>
<head>
    <title>{{ $notification->title ?? 'Imóvel Encontrado' }}</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f4f4f4;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <div style="background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <!-- Header -->
            <div style="background: linear-gradient(135deg, #3182ce 0%, #2c5282 100%); padding: 30px; text-align: center;">
                <h1 style="color: #ffffff; margin: 0; font-size: 24px;">Encontramos um imóvel para você!</h1>
            </div>

            <!-- Content -->
            <div style="padding: 30px;">
                <p style="margin-top: 0;">Olá{{ $intention->name ? ', ' . $intention->name : '' }}!</p>

                <p>Com base nas suas preferências, encontramos um imóvel que pode ser perfeito para você:</p>

                @if($property)
                <div style="background-color: #f7fafc; border-radius: 8px; padding: 20px; margin: 20px 0; border-left: 4px solid #3182ce;">
                    <h2 style="margin-top: 0; color: #2d3748; font-size: 18px;">
                        {{ $property->title ?? $property->tipo . ' - ' . $property->bairro }}
                    </h2>

                    <div style="color: #4a5568; font-size: 14px;">
                        @if($property->endereco)
                        <p style="margin: 5px 0;"><strong>Endereço:</strong> {{ $property->endereco }}{{ $property->bairro ? ', ' . $property->bairro : '' }}</p>
                        @endif

                        @if($property->cidade)
                        <p style="margin: 5px 0;"><strong>Cidade:</strong> {{ $property->cidade }}{{ $property->estado ? ' - ' . $property->estado : '' }}</p>
                        @endif

                        <div style="display: flex; flex-wrap: wrap; gap: 15px; margin-top: 15px;">
                            @if($property->quartos)
                            <span><strong>Quartos:</strong> {{ $property->quartos }}</span>
                            @endif

                            @if($property->banheiros)
                            <span><strong>Banheiros:</strong> {{ $property->banheiros }}</span>
                            @endif

                            @if($property->vagas)
                            <span><strong>Vagas:</strong> {{ $property->vagas }}</span>
                            @endif

                            @if($property->area_total)
                            <span><strong>Área:</strong> {{ $property->area_total }}m²</span>
                            @endif
                        </div>

                        @if($property->valor_venda || $property->valor_locacao)
                        <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #e2e8f0;">
                            @if($property->valor_venda)
                            <p style="margin: 5px 0; font-size: 18px; color: #2b6cb0;">
                                <strong>Venda:</strong> R$ {{ number_format($property->valor_venda, 2, ',', '.') }}
                            </p>
                            @endif

                            @if($property->valor_locacao)
                            <p style="margin: 5px 0; font-size: 18px; color: #2b6cb0;">
                                <strong>Aluguel:</strong> R$ {{ number_format($property->valor_locacao, 2, ',', '.') }}/mês
                            </p>
                            @endif
                        </div>
                        @endif
                    </div>
                </div>
                @endif

                @if($notification->action_url)
                <div style="text-align: center; margin: 30px 0;">
                    <a href="{{ $notification->action_url }}"
                       style="background-color: #3182ce; color: white; padding: 14px 28px; text-decoration: none; border-radius: 6px; font-weight: bold; display: inline-block;">
                        Ver Imóvel
                    </a>
                </div>
                @endif

                <p style="color: #718096; font-size: 14px;">
                    {{ $notification->message }}
                </p>
            </div>

            <!-- Footer -->
            <div style="background-color: #f7fafc; padding: 20px; text-align: center; border-top: 1px solid #e2e8f0;">
                <p style="margin: 0; color: #718096; font-size: 12px;">
                    Você está recebendo este email porque solicitou alertas de imóveis no SOCIMOB.
                </p>
                <p style="margin: 10px 0 0; color: #a0aec0; font-size: 11px;">
                    Para deixar de receber estes alertas, acesse suas preferências de notificação.
                </p>
            </div>
        </div>
    </div>
</body>
</html>
