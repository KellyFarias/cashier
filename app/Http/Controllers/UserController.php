<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use DB;
use App\Models\User;
use Laravel\Cashier\Cashier;
use Throwable;
class UserController extends Controller
{
    public function create(Request $request)
    {
        DB::beginTransaction();
        try {
            $user = new User();
            $user->fill($request->all());
            $user->saveOrFail();
            $user->createAsStripeCustomer();
            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Cliente creado correctamente',
                'user' => $user,
            ]);
        } catch (Throwable $th) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al borrar cliente',
                'errorFile' => $th->getFile(),
                'errorLine' => $th->getLine(),
                'errorMessage' => $th->getMessage(),
            ]);
        }
    }

    public function update(Request $request, $customerId)
    {
        DB::beginTransaction();
        try {
            $user = User::findOrFail($customerId);
            $user->name = $request->name;
            $user->saveOrFail();

            $options = ['name' => $request->name];
            $user->updateStripeCustomer($options);

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Cliente actualizado correctamente',
                'user' => $user,
            ]);
        } catch (Throwable $th) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar cliente',
                'errorFile' => $th->getFile(),
                'errorLine' => $th->getLine(),
                'errorMessage' => $th->getMessage(),
            ]);
        }
    }

    public function updateBridge(Request $request, $customerId)
    {
        DB::beginTransaction();
        try {
            $user = User::findOrFail($customerId);
            $stripeCustomer = $user->asStripeCustomer();

            $user->name = $request->name;
            $stripeCustomer->name = $request->name;

            $user->saveOrFail();
            $stripeCustomer->save();

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Cliente actualizado correctamente',
                'user' => $user,
            ]);
        } catch (Throwable $th) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar cliente',
                'errorFile' => $th->getFile(),
                'errorLine' => $th->getLine(),
                'errorMessage' => $th->getMessage(),
            ]);
        }
    }

    public function updateSync(Request $request, $customerId)
    {
        DB::beginTransaction();
        try {
            $user = User::findOrFail($customerId);
            $user->name = $request->name;
            $user->saveOrFail();
            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Cliente actualizado correctamente',
                'user' => $user,
            ]);
        } catch (Throwable $th) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar cliente',
                'errorFile' => $th->getFile(),
                'errorLine' => $th->getLine(),
                'errorMessage' => $th->getMessage(),
            ]);
        }
    }


    public function delete($customerId)
    {
        DB::beginTransaction();
        try {

            // $user = User::findOrFail($customerId);
            // $stripeCustomer = $user->asStripeCustomer();
            // $stripeCustomer->delete();
            // $user->delete();

            $user = User::findOrFail($customerId);
            Cashier::stripe()->customers->delete($user->stripe_id, []);
            $user->delete();

            Cashier::stripe()->paymentIntents->create([
                'amount' => 2000,
                'currency' => 'mxn',
                'automatic_payment_methods' => [
                    'enabled' => true,
                ],
            ]);

            $card = Cashier::stripe()->paymentMethods->create([
                'type' => 'card',
                'card' => [
                    'number' => '4242424242424242',
                    'exp_month' => 8,
                    'exp_year' => 2020,
                    'cvc' => '314',
                ],
            ]);

            $user->addPaymentMethod($card->id);

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Cliente borrado correctamente',
            ]);
        } catch (Throwable $th) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al borrar cliente',
                'errorFile' => $th->getFile(),
                'errorLine' => $th->getLine(),
                'errorMessage' => $th->getMessage(),
            ]);
        }
    }

    public function upsert(Request $request, $customerId = 0)
    {
        DB::beginTransaction();
        try {
            $user = [];
            foreach ($request->all() as $parameter => $value) {
                if (isset($value)) $user += [$parameter => $value];
            }
            $user = User::updateOrCreate(['id' => $customerId], $user);
            $user->saveOrFail();
            if (!$user->hasStripeId()) $user->createAsStripeCustomer();
            DB::commit();
            return response()
                ->json([
                    'success' => true,
                    'message' => 'Usuario registrado correctamente'
                ]);
        } catch (Throwable $th) {
            DB::rollback();
            return response()
                ->json([
                    'success' => false,
                    'message' => 'Error al crear usuario',
                    'error_file' => $th->getFile(),
                    'error_line' => $th->getLine(),
                    'error_message' => $th->getMessage(),
                ]);
        }
    }

    public function createCard(Request $request, $customerId)
    {
        try {
            $user = User::findOrFail($customerId);
            $paymentMethod = Cashier::stripe()->paymentMethods->create($request->all());            
            $user->addPaymentMethod($paymentMethod);
            $user->updateDefaultPaymentMethodFromStripe();
            if (!$user->hasDefaultPaymentMethod()) {
                $user->updateDefaultPaymentMethod($paymentMethod);
            }
            return response()
                ->json([
                    'success' => true,
                    'message' => 'Tarjeta creada correctamente'
                ]);
        } catch (Throwable $th) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Error al crear tarjeta',
                'error_file' => $th->getFile(),
                'error_line' => $th->getLine(),
                'error_message' => $th->getMessage(),
            ]);
        }
    }

    public function getCards($customerId)
    {
        try {
            $user = User::findOrFail($customerId);
            if (!$user->hasPaymentMethod()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tiene tarjetas aun'
                ]);
            }
            return response()->json([
                'cards' => $user->paymentMethods(),
                'success' => true,
                'message' => 'Tarjeta creada correctamente'
            ]);
        } catch (Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Error al encontrar tarjetas',
                'error_file' => $th->getFile(),
                'error_line' => $th->getLine(),
                'error_message' => $th->getMessage(),
            ]);
        }
    }

    public function getCardDefault($customerId)
    {
        try {
            $user = User::findOrFail($customerId);
            $user->updateDefaultPaymentMethodFromStripe();
            if (!$user->hasPaymentMethod()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tiene tarjetas registradas'
                ]);
            } else if (!$user->hasDefaultPaymentMethod()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No existe tarjeta por defecto'
                ]);
            }
            return response()->json([
                'card' => $user->defaultPaymentMethod()->card,
                'success' => true,
                'message' => 'Tarjeta encontrada'
            ]);
        } catch (Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Error al encontrar tarjeta por defecto',
                'error_file' => $th->getFile(),
                'error_line' => $th->getLine(),
                'error_message' => $th->getMessage(),
            ]);
        }
    }


   
    public function paymentPMDefault(Request $request, $customerId)
    {
        try {
            $user = User::findOrFail($customerId);
            $user->updateDefaultPaymentMethodFromStripe();
            if (!$user->hasPaymentMethod()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tiene tarjetas registradas'
                ]);
            } else if (!$user->hasDefaultPaymentMethod()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No existe tarjeta por defecto'
                ]);
            }
            $user->charge(($request->amount * 100), $user->defaultPaymentMethod()->id);
            return response()->json([
                'success' => true,
                'message' => 'Pago realizado correctamente'
            ]);
        } catch (Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Error al hacer el pago',
                'error_file' => $th->getFile(),
                'error_line' => $th->getLine(),
                'error_message' => $th->getMessage(),
            ]);
        }
    }

public function paymentGuest(Request $request)
{
    try {

        $paymentMethod = Cashier::stripe()
            ->paymentMethods
            ->create(['type' => $request->type, 'card' => $request->card, 'billing_details' => ['email' => $request->email]]);

        (new User())->charge(($request->amount * 100), $paymentMethod->id);
        return response()->json([
            'success' => true,
            'message' => 'Pago de invitado realizado correctamente'
        ]);
    } catch (Throwable $th) {
        return response()->json([
            'success' => false,
            'message' => 'Error al hacer el pago',
            'error_file' => $th->getFile(),
            'error_line' => $th->getLine(),
            'error_message' => $th->getMessage(),
        ]);
    }
}

public function paymentIntent(Request $request,$userId){
    //laravel 9
    //$payment=$user->pay($request->get('amount'));

  
    try {
        $user=User::findOrFail($userId);
       /* $paymentMethod = Cashier::stripe()
        ->paymentMethods
        ->create(['type' => $request->type,
         'card' => $request->card, 
         'billing_details' => ['name' => $user->name]]);
         $intent =$user->createSetupIntent();
         $confirmIntent= Cashier::stripe()->setupIntents->confirm($intent->id,['payment_method'=>$paymentMethod->id]);
        
       if ($confirmIntent->status == 'succeded'){
        $intentVerify = Cashier::stripe()->setupIntents->verifyMicrodeposits($intent->id,[32,45]);
       }else{
        $intentVerify = 'Fallo'
       }*/
       $paymentMethod = Cashier::stripe()
        ->paymentMethods
        ->create(['type' => $request->type,
         'card' => $request->card, 
         'billing_details' => ['name' => $user->name]]);
       $paymentIntent=Cashier::stripe()->paymentIntents->create([
        'amount'=>$request->amount*100,
        'currency'=>'mxn',
        'customer'=>$user->stripe_id,
        'payment_method'=>$paymentMethod,
        'confirm'=>true
       ]);
       //$confirm=Cashier::stripe()->paymentIntents->confirm($paymentIntent->id);
        return response()->json([
            'success' => true,
            'message' => 'Pago realizado',
            'intent'=>$paymentIntent->status
        ]);
    } catch (Throwable $th) {
        return response()->json([
            'success' => false,
            'message' => 'Error al hacer el pago',
            'error_file' => $th->getFile(),
            'error_line' => $th->getLine(),
            'error_message' => $th->getMessage(),
        ]);
    }

}
}
