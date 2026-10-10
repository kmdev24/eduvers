@props(['status'])

@use('App\Enums\EnrollmentStatus')

<span @class([
    'badge-slate'  => $status === EnrollmentStatus::Pending,
    'badge-gold'   => $status === EnrollmentStatus::Approved,
    'inline-flex items-center rounded-full bg-red-50 px-2.5 py-0.5 text-xs font-medium text-red-700 ring-1 ring-red-200' => $status === EnrollmentStatus::Rejected,
])>{{ $status->label() }}</span>
