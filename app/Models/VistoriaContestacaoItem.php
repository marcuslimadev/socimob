<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VistoriaContestacaoItem extends Model
{
    protected $table = 'vistoria_contestacao_itens';

    protected $fillable = ['contestacao_id', 'ambiente_id', 'item_id', 'inconformidade_id', 'descricao'];
}
