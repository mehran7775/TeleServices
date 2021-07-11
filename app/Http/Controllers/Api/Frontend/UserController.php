<?php

namespace App\Http\Controllers\Api\Frontend;


use App\Http\Requests\api\User\UserRegisterRequest;
use App\Http\Resources\Api\LoginRecource;
use App\Jobs\SendEmailNotify;
use App\Mail\UserLogin;
use App\Mail\UserRegister;
use App\Models\Address;
use App\Models\CodeVerify;
use App\User;
use Carbon\Carbon;
use Faker\Provider\File;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
//use Intervention\Image\Facades\Image;
use Illuminate\Support\Facades\Validator;
use phpDocumentor\Reflection\Location;
use Symfony\Component\Mime\Header\MailboxHeader;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    public function register()
    {
        $csrf_token = csrf_token();
        return response()->json([
            'result' => 'ok',
            'csrf_token' => $csrf_token
        ]);
    }

    public function doRegister(UserRegisterRequest $request)
    {
        $validated = $request->validated();
        if (!$validated) {
            return response()->json(['message' => $validated->errors()]);
        } else {
            User::create([
                'name' => $request->input('name'),
                'username' => $request->input('username'),
                //                'password' => Hash::make(Request::get('password')),
                'password' => Hash::make($request->input('password')),
                'email' => $request->input('email'),
            ]);
            return response()->json([
                'success' => trans('api.user.register.success'),
            ], 201);
        }
    }

    public function checkFastLogin()
    {
        $user = new User();
        $username = $user->username;
        $password = $user->password;
        return response()->json([
            $username, $password
        ]);
    }

    public function page_password_resets()
    {
        return response()->json(['result' => 'ok']);
    }

    public function password_resets(UserRegisterRequest $request)
    {
        if ($user = User::where('email', $request->input('email'))->first()) {
            $code_verify = Str::random(5);
            $user->update([
                'password' => bcrypt($code_verify)
            ]);
            //            CodeVerify::where('user_id', $user->id)->update([
            //                'code_verify_login' => $code_verify,
            //                'expired_at' => Carbon::now()->addSeconds(90),
            //            ]);
            Mail::to($user->email)->send(new UserLogin($user));
            return response()->json(['result' => 'Sent codeVerify successfully']);
        } else {
            return response()->json(['errorUser' => 'user noRegistered'], 404);
        }
    }

    public function login()
    {
        return response()->json(['result' => 'ok'], 200);
    }

    public function doLogin(Request $request)
    {
        $this->validate($request, [
            'username' => 'required|min:4,max:12',
            'password' => 'required|min:6',
        ]);
        $remember = $request->has('remember');
        $credentials =array(
            "username" => $request->input('username'),
            "password" => $request->input('password')
        );
        if (Auth::attempt($credentials, $remember)) {
            $token = Auth::user()->createToken('Personal Access Token')->accessToken;
            $user = Auth::user();
            return Response()->json([
                'success' => trans('api.user.login.success'),
                'token' => $token,
                'user' => new LoginRecource($user)
            ]);
        } else {
            return response()->json([
                'failed' => trans('api.user.login.failed')
            ], 404);
        }
    }

    // public function get_all_user()
    // {
    //     $users = User::all();
    //     return response()->json(
    //         [
    //             'users' => $users
    //         ]
    //     );
    // }

    public function loginWithEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email|min:12,max:30'
        ]);
        if ($user = User::where('email', $request->input('email'))->first()) {
            $code_verify = Str::random(6);
            $object = CodeVerify::create([
                'user_id' => $user->id,
                'code_verify_login' => $code_verify,
                'expired_at' => Carbon::now()->addSeconds(90)
            ]);
            Mail::to($user->email)->send(new UserLogin($user));
            // $code = Str::random(40);
            // $user->update([
            //     'code' => $code
            // ]);
            return response()->json([
                'result' => 'رمز عبور به ایمیلتان ارسال شد.'
            ]);
        } else {
            return response()->json(['errorEmail' => 'ایمیل وارد شده در سیستم ثبت نشده است'], 404);
        }
    }

    // public function showloginWithEmail()
    // {
    //     return response()->json(['result' => 'ok']);
    // }

    public function doLoginWithEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email|min:12,max:30',
            'password' => 'required|min:6,max:16'
        ]);
        if ($user = User::where('email', $request->input('email'))->first()) {
            // $remember = $request->has('remember');
            // $credentials = $request('password');
            $code = $user->codeVerifies()->pluck('code_verify_login')->first();
            if ($request->input('password') === $code) {
                $token = $user->createToken('Personal Access Token')->accessToken;
                return Response()->json([
                    'success' => trans('api.user.login_withEmail.success'),
                    'token' => $token,
                    'user' => new LoginRecource($user)
                ]);
            } else {
                return response()->json([
                    'failed' => trans('api.user.login_withEmail.failed')
                ], 404);
            }
        } else {
            return response()->json(['errorEmail' => 'ایمیل وارد شده در سیستم ثبت نشده است'], 404);
        }
        // $user = User::where('code', $code)->first();
        // $time = $user->codeVerifies()->pluck('expired_at')->toArray();
        // $object = new CodeVerify();
        // if (Carbon::now()->toDateTimeString() < $time[0]) {
        //     $code_verify = $user->codeVerifies()->pluck('code_verify_login')->first();
        //     $code_email = $request->input('code_email');
        //     if ($code_email == $code_verify) {
        //         $newUser = Auth::loginUsingId($user->id);
        //         $token = $newUser->createToken('Personal Access Token')->accessToken;
        //         return response()->json(['token' => $token]);
        //     } else {
        //         return response()->json(['errorCode' => 'Invalid CodeVerify']);
        //     }
        // } else {
        //     CodeVerify::where('user_id', $user->id)->delete();
        //     return response()->json(['errorTime' => 'Time is up'], 400);
        // }
    }

    public function logOut(Request $request)
    {
        Auth::user()->token()->revoke();
        return response()->json(['message' => trans('api.user.logout.success')]);
    }
    public function get_wallet()
    {
        $user = Auth::user();
        if ($wallet = DB::table('wallets')->where('user_id', $user->id)->first()) {
            $amount = $wallet->amount;
            return Response([
                'wallet' => $amount
            ], 200);
        } else {
            return Response([
                'wallet' => 0
            ]);
        }
    }

    // public function authCheck()
    // {
    //     if (Auth::check()) {
    //         return response()->json(['result' => true]);
    //     }
    //     if ($user = Auth::user() != null) {
    //         return response()->json($user);
    //     }
    //     return response()->json(['message' => 'Unauthenticated'], 401);
    // }
}
