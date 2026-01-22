<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use App\Models\User;
use App\Mail\ForgotPassword;

class PasswordResetController extends Controller
{
    /**
     * Send reset link
     * POST /api/auth/forgot-password
     */
    public function sendResetLink(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email'
        ], [
            'email.required' => 'O email é obrigatório',
            'email.email' => 'Email inválido',
            'email.exists' => 'Não encontramos um usuário com este email'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erro de validação',
                'errors' => $validator->errors()
            ], 422);
        }

        $email = $request->email;
        $token = Str::random(64);

        // Delete old tokens
        DB::table('password_resets')->where('email', $email)->delete();

        // Create new token
        DB::table('password_resets')->insert([
            'email' => $email,
            'token' => $token,
            'created_at' => Carbon::now()
        ]);

        // Send email
        try {
            Mail::to($email)->send(new ForgotPassword($token, $email));

            return response()->json([
                'success' => true,
                'message' => 'Link de recuperação enviado com sucesso! Verifique seu email.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao enviar email de recuperação',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reset password
     * POST /api/auth/reset-password
     */
    public function reset(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
            'password' => 'required|string|min:6|confirmed',
            'token' => 'required|string'
        ], [
            'password.required' => 'A nova senha é obrigatória',
            'password.min' => 'A senha deve ter no mínimo 6 caracteres',
            'password.confirmed' => 'A confirmação de senha não confere',
            'token.required' => 'Token inválido ou expirado'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Dados inválidos',
                'errors' => $validator->errors()
            ], 422);
        }

        $passwordReset = DB::table('password_resets')
            ->where([
                ['email', $request->email],
                ['token', $request->token]
            ])
            ->first();

        if (!$passwordReset) {
            return response()->json([
                'success' => false,
                'message' => 'Token inválido ou expirado'
            ], 400);
        }
        
        // Check expiration (60 mins)
        if (Carbon::parse($passwordReset->created_at)->addMinutes(60)->isPast()) {
             DB::table('password_resets')->where('email', $request->email)->delete();
             
             return response()->json([
                'success' => false,
                'message' => 'Token expirado. Solicite uma nova redefinição.'
            ], 400);
        }

        $user = User::where('email', $request->email)->first();
        
        if (!$user) {
             return response()->json([
                'success' => false,
                'message' => 'Usuário não encontrado'
            ], 404);
        }

        $user->password = Hash::make($request->password);
        $user->save();

        DB::table('password_resets')->where('email', $request->email)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Senha alterada com sucesso! Faça login com sua nova senha.'
        ]);
    }
}
