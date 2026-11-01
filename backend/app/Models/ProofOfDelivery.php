<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $receiver_name
 * @property string|null $photo_path
 * @property string|null $signature_path
 * @property string|null $document_path
 * @property string $source
 */
class ProofOfDelivery extends Model
{
    protected $fillable = ['shipment_id', 'receiver_name', 'photo_path', 'signature_path', 'document_path', 'source', 'user_id'];
}
