@extends('layouts.admin.main')

@section('content')
<div class="p-6 bg-gray-900 min-h-screen text-white">
    <div class="max-w-7xl mx-auto">
        
        <div class="flex items-center justify-between mb-6 border-b border-gray-700 pb-3">
            <div class="flex items-center gap-3">
                <i class="bi bi-newspaper text-3xl text-blue-400"></i>
                <h1 class="text-3xl font-bold text-gray-100">Quản Lý Tin Tức</h1>
            </div>
            <a href="{{ route('news.create') }}" 
                class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded-lg flex items-center gap-2 transition-colors duration-300 shadow-md">
                <i class="bi bi-plus-lg"></i> Thêm mới
            </a>
        </div>

        @if(session('success'))
            <div class="bg-green-500/10 border border-green-500/20 text-green-400 p-4 rounded-lg mb-6 flex items-center gap-3">
                <i class="bi bi-check-circle-fill text-xl"></i>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        {{-- START: Form tìm kiếm (Giống Users Index) --}}
        <div class="bg-gray-800 rounded-xl shadow-lg border border-gray-700 p-6 mb-6">
            <form action="{{ route('news.index') }}" method="GET">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    {{-- Trường Tìm kiếm --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">Tìm kiếm</label>
                        <input type="text" name="search" value="{{ request('search') }}"
                            class="w-full bg-gray-600 border border-gray-500 rounded-lg px-4 py-2 text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            placeholder="Tìm theo tiêu đề...">
                    </div>
                    
                    {{-- Trường lọc theo trạng thái (Giả định có cột status) --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">Trạng thái</label>
                        <select name="status" 
                            class="w-full bg-gray-600 border border-gray-500 rounded-lg px-4 py-2 text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">Tất cả trạng thái</option>
                            <option value="1" {{ request('status') == '1' ? 'selected' : '' }}>Hoạt động</option>
                            <option value="0" {{ request('status') == '0' ? 'selected' : '' }}>Ẩn</option>
                        </select>
                    </div>

                    {{-- Nút Tìm kiếm và Reset --}}
                    <div class="flex items-end gap-2">
                        <button type="submit" 
                            class="w-full bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg flex items-center justify-center gap-2 transition-colors">
                            <i class="bi bi-search"></i> Tìm kiếm
                        </button>
                        <a href="{{ route('news.index') }}" 
                            class="w-full bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded-lg flex items-center justify-center gap-2 transition-colors">
                            <i class="bi bi-arrow-counterclockwise"></i> Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>
        {{-- END: Form tìm kiếm --}}

        {{-- Thông báo số lượng (Giống Users Index) --}}
        <div class="bg-blue-500/10 border border-blue-500/20 text-blue-400 p-4 rounded-lg mb-6">
            Hiển thị {{ $news->count() }} tin tức trên tổng số {{ $news->total() }} tin tức.
        </div>

        <div class="bg-gray-800 rounded-xl shadow-lg border border-gray-700 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-700">
                    <thead class="bg-gray-700/50"> {{-- Sửa màu header table --}}
                        <tr>
                            <th class="px-6 py-4 text-left text-sm font-medium text-gray-300 w-1/12">ID</th> {{-- Sửa lại padding --}}
                            <th class="px-6 py-4 text-left text-sm font-medium text-gray-300 w-2/3">Tiêu đề</th>
                            <th class="px-6 py-4 text-left text-sm font-medium text-gray-300 w-1/6">Trạng thái</th> {{-- Thêm cột Trạng thái --}}
                            <th class="px-6 py-4 text-left text-sm font-medium text-gray-300 w-1/12">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700"> {{-- Sửa màu divider --}}
                        @forelse($news as $new)
                            <tr class="hover:bg-gray-700/30 transition-colors"> {{-- Sửa hover color --}}
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-blue-400">{{ $new->id }}</td>
                                <td class="px-6 py-4 whitespace-normal text-sm text-gray-300 font-medium">
                                    {{ $new->title }}
                                </td>
                                
                                {{-- Thêm cột Trạng thái (Giả định cột is_active tồn tại) --}}
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 text-xs font-medium rounded-full {{ $new->is_active ? 'bg-green-500/10 text-green-400' : 'bg-red-500/10 text-red-400' }}">
                                        {{ $new->is_active ? 'Hoạt động' : 'Ẩn' }}
                                    </span>
                                </td>

                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium flex gap-2 items-center">
                                    {{-- Nút Chỉnh sửa --}}
                                    <a href="{{ route('news.edit', $new) }}" 
                                        class="text-yellow-400 hover:text-yellow-300 p-2 rounded-full hover:bg-yellow-500/10 transition-colors"
                                        title="Chỉnh sửa">
                                        <i class="bi bi-pencil-square text-lg"></i>
                                    </a>
                                    
                                    {{-- Form Xóa --}}
                                    <form action="{{ route('news.destroy', $new) }}" method="POST" onsubmit="return confirm('Bạn có chắc chắn muốn xóa tin tức này không?')" style="display:inline;">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" 
                                            class="text-red-400 hover:text-red-300 p-2 rounded-full hover:bg-red-500/10 transition-colors"
                                            title="Xóa">
                                            <i class="bi bi-trash-fill text-lg"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-4 text-center text-gray-400"> {{-- Sửa colspan thành 4 --}}
                                    Không tìm thấy tin tức nào.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-6 flex justify-center">
            {{ $news->links() }}
        </div>
    </div>
</div>
@endsection