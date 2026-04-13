<?php

namespace App\Models\WhatsApp;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;

class WhatsAppMedia extends Model
{
    protected $table = 'whatsapp_media';

    protected $fillable = [
        'tenant_id',
        'whatsapp_account_id',
        'whatsapp_phone_number_id',
        'whatsapp_message_id',
        'meta_media_id',
        'direction',
        'media_type',
        'mime_type',
        'sha256',
        'filename',
        'caption',
        'storage_disk',
        'storage_path',
        'download_url',
        'file_size',
        'metadata',
        'uploaded_at',
        'downloaded_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'uploaded_at' => 'datetime',
        'downloaded_at' => 'datetime',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function account()
    {
        return $this->belongsTo(WhatsAppAccount::class, 'whatsapp_account_id');
    }

    public function phoneNumber()
    {
        return $this->belongsTo(WhatsAppPhoneNumber::class, 'whatsapp_phone_number_id');
    }

    public function message()
    {
        return $this->belongsTo(WhatsAppMessage::class, 'whatsapp_message_id');
    }
}
