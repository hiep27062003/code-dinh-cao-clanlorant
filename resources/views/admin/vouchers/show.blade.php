@extends('layouts.admin.main')

@section('content')
<div class="p-6 bg-gray-900 min-h-screen text-white">
    <div class="max-w-4xl mx-auto">
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-2">
                <i class="bi bi-tag text-blue-400 text-2xl"></i>
                <h1 class="text-2xl font-bold text-blue-400">Chi Tiết Voucher</h1>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('discounts.edit', $voucher->id) }}" 
                    class="bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded-lg flex items-center gap-2 transition-colors">
                    <i class="bi bi-pencil"></i> Chỉnh sửa
                </a>
                <a href="{{ route('discounts.index') }}" 
                    class="bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded-lg flex items-center gap-2 transition-colors">
                    <i class="bi bi-arrow-left"></i> Danh sách
                </a>
            </div>
        </div>

        <div class="bg-gray-800 rounded-xl shadow-lg border border-gray-700 p-8">
            
            <h2 class="text-xl font-semibold mb-6 text-gray-300 border-b border-gray-700 pb-2">Thông tin Voucher</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                <div class="space-y-4">
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-400 mb-1">Mã Code</label>
                        <div class="bg-gray-700 rounded-lg px-4 py-2 text-white font-mono tracking-wider">
                            {{ $voucher->code }}
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-400 mb-1">Loại Voucher</label>
                        <div class="px-3 py-1 text-sm font-medium rounded-full inline-block 
                            {{ $voucher->discount_type == 'percentage' ? 'bg-purple-500/10 text-purple-400' : 'bg-green-500/10 text-green-400' }}">
                            {{ $voucher->discount_type == 'percentage' ? 'Phần trăm (%)' : 'Cố định (đ)' }}
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-400 mb-1">Giá trị</label>
                        <div class="bg-gray-700 rounded-lg px-4 py-2 text-white">
                            @if($voucher->discount_type == 'percentage')
                                {{ $voucher->discount_value }}%
                            @else
                                {{ number_format($voucher->discount_value, 0, ',', '.') }}đ
                            @endif
                        </div>
                    </div>
                    
                    @if($voucher->discount_type == 'percentage')
                        <div>
                            <label class="block text-sm font-medium text-gray-400 mb-1">Giảm tối đa</label>
                            <div class="bg-gray-700 rounded-lg px-4 py-2 text-white">
                                @if($voucher->max_discount_value)
                                    {{ number_format($voucher->max_discount_value, 0, ',', '.') }}đ
                                @else
                                    Không giới hạn
                                @endif
                            </div>
                        </div>
                    @endif

                </div>

                <div class="space-y-4">
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-400 mb-1">Đơn hàng tối thiểu</label>
                        <div class="bg-gray-700 rounded-lg px-4 py-2 text-white">
                            @if($voucher->min_order_value > 0)
                                {{ number_format($voucher->min_order_value, 0, ',', '.') }}đ
                            @else
                                Không yêu cầu
                            @endif
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-400 mb-1">Ngày Bắt Đầu</label>
                        <div class="bg-gray-700 rounded-lg px-4 py-2 text-white">
                            {{ \Carbon\Carbon::parse($voucher->start_date)->format('d/m/Y') }}
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-400 mb-1">Ngày Kết Thúc</label>
                        <div class="bg-gray-700 rounded-lg px-4 py-2 text-white">
                            {{ \Carbon\Carbon::parse($voucher->expiration_date)->format('d/m/Y') }}
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-400 mb-1">Trạng thái</label>
                        @php
                            $now = now();
                            $start = \Carbon\Carbon::parse($voucher->start_date);
                            $end = \Carbon\Carbon::parse($voucher->expiration_date);
                            
                            if ($now->isBetween($start, $end)) {
                                $statusClass = 'bg-green-500/10 text-green-400';
                                $statusText = 'Đang hoạt động';
                            } elseif ($now->isBefore($start)) {
                                $statusClass = 'bg-blue-500/10 text-blue-400';
                                $statusText = 'Sắp hoạt động';
                            } else {
                                $statusClass = 'bg-red-500/10 text-red-400';
                                $statusText = 'Đã hết hạn';
                            }
                        @endphp
                        <div class="px-3 py-1 text-sm font-medium rounded-full inline-block {{ $statusClass }}">
                            {{ $statusText }}
                        </div>
                    </div>
                    
                </div>
            </div>
            
        </div>
    </div>
</div>
@endsection