<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('commissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('corretor_id')->comment('ID do usuário corretor');
            $table->decimal('valor_venda', 15, 2)->comment('Valor total da venda do imóvel');
            $table->decimal('percentual', 5, 2)->comment('Percentual da comissão (ex: 6.00 para 6%)');
            $table->decimal('valor_comissao', 15, 2)->comment('Valor calculado da comissão');
            $table->enum('status', ['pendente', 'processando', 'pago', 'cancelado'])->default('pendente');
            $table->text('observacoes')->nullable();
            
            // Dados do pagamento via Mercado Pago
            $table->string('mercadopago_payment_id')->nullable()->comment('ID do pagamento no Mercado Pago');
            $table->string('mercadopago_qrcode')->nullable()->comment('Código PIX copia e cola');
            $table->text('mercadopago_qrcode_base64')->nullable()->comment('QR Code em base64');
            $table->timestamp('pago_em')->nullable()->comment('Data/hora de confirmação do pagamento');
            
            // Dados do comprovante
            $table->string('comprovante_path')->nullable()->comment('Caminho do PDF do comprovante');
            
            // Dados da NFSe via NFE.io
            $table->string('nfe_io_id')->nullable()->comment('ID da nota fiscal no NFE.io');
            $table->string('nfse_numero')->nullable()->comment('Número da NFSe emitida');
            $table->string('nfse_pdf_url')->nullable()->comment('URL do PDF da NFSe');
            $table->timestamp('nfse_emitida_em')->nullable()->comment('Data/hora de emissão da NFSe');
            
            $table->timestamps();
            
            // Índices
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('corretor_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['tenant_id', 'status']);
            $table->index('corretor_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commissions');
    }
};
