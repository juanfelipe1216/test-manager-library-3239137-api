<?php
namespace App\Http\Middleware;
use App\Application\Ports\TokenVerifier;
use Closure;
use Illuminate\Http\Request;
final class ApiAuth {
    public function __construct(private TokenVerifier $tokens) {}
    public function handle(Request $request,Closure $next,?string $role=null) {
        try { $claims=$this->tokens->verify($request->bearerToken()??''); }
        catch(\Throwable) { return response()->json(['code'=>'UNAUTHENTICATED','message'=>'Debes iniciar sesión.','details'=>[]],401); }
        if($role!==null && $claims['role']!==$role) return response()->json(['code'=>'FORBIDDEN_ROLE','message'=>'No tienes permiso para esta operación.','details'=>[]],403);
        $request->attributes->set('identity',$claims);
        return $next($request);
    }
}