<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Models\{Property, Person, Occupancy, AuditLog};
use App\Services\{FinanceService, Money};

class CommunityController extends Controller
{
    public function property(Request $request, ?Property $property = null): JsonResponse
    {
        abort_unless($request->user()->allows('community.manage'), 403);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:30', Rule::unique('properties')->ignore($property?->id)],
            'occupancy_status' => ['required', Rule::in(['occupied', 'rented', 'vacant'])],
            'active' => ['required', 'boolean'],
        ]);
        $property ??= new Property;
        $property->fill($data)->save();
        AuditLog::record('save', $property, $data);
        return response()->json($property);
    }

    public function person(Request $request, ?Person $person = null): JsonResponse
    {
        abort_unless($request->user()->allows('community.manage'), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'identity_number' => ['required', 'string', 'max:40', Rule::unique('people')->ignore($person?->id)],
            'email' => ['nullable', 'email', 'max:200'],
            'phone' => ['nullable', 'string', 'max:40'],
        ]);
        $person ??= new Person;
        $person->fill($data)->save();
        AuditLog::record('save', $person);
        return response()->json($person);
    }

    public function occupancy(Request $request, ?Occupancy $occupancy = null): JsonResponse
    {
        abort_unless($request->user()->allows('community.manage'), 403);
        $data = $request->validate([
            'property_id' => ['required', 'exists:properties,id'],
            'person_id' => ['required', 'exists:people,id'],
            'relationship' => ['required', Rule::in(['owner', 'tenant', 'resident'])],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
        ]);
        $occupancy ??= new Occupancy;
        $occupancy->fill($data)->save();
        AuditLog::record('save', $occupancy, $data);
        return response()->json($occupancy);
    }

    public function opening(Request $request, Property $property, FinanceService $finance): JsonResponse
    {
        abort_unless($request->user()->allows('finance.manage'), 403);
        $data = $request->validate([
            'amount_usd' => ['required', 'numeric', 'between:-10000000,10000000', 'regex:/^-?\d+(\.\d{1,2})?$/'],
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
        ]);
        DB::transaction(function () use ($finance, $property, $data, $request): void {
            $finance->setOpeningBalance($property, Money::parse((string) $data['amount_usd']), $data['date'], $request->user()->id);
            AuditLog::record('opening', $property, $data);
        });
        return response()->json(['message' => 'Saldo inicial registrado.']);
    }

    public function statement(Request $request, Property $property, FinanceService $finance): JsonResponse
    {
        abort_unless($request->user()->allows('finance.view') && $request->user()->canAccessProperty($property->id), 403);
        return response()->json($finance->propertyStatement($property));
    }
}
