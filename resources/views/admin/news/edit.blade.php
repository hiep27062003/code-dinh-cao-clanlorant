@extends('layouts.admin.main')

@section('content')
<div class="p-6 bg-gray-900 min-h-screen text-white">
    <div class="max-w-4xl mx-auto">
        
        <div class="flex items-center justify-between mb-6 border-b border-gray-700 pb-3">
            <div class="flex items-center gap-3">
                <i class="bi bi-pencil-square text-3xl text-yellow-400"></i>
                <h1 class="text-3xl font-bold text-gray-100">Chỉnh Sửa Tin Tức</h1>
            </div>
            <a href="{{ route('news.index') }}" 
                class="bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded-lg flex items-center gap-2 transition-colors">
                <i class="bi bi-arrow-left"></i> Quay lại
            </a>
        </div>

        <form action="{{ route('news.update', $news->id) }}" method="POST" enctype="multipart/form-data" class="bg-gray-800 p-6 rounded-xl shadow-lg border border-gray-700 space-y-6">
            @csrf
            @method('PUT')
            
            <div>
                <label for="title" class="block text-sm font-medium text-gray-300 mb-1">Tiêu đề</label>
                <input type="text" name="title" id="title" 
                       class="w-full bg-gray-700 border border-gray-600 rounded-lg p-3 text-white focus:ring-blue-500 focus:border-blue-500 @error('title') border-red-500 @enderror" 
                       value="{{ old('title', $news->title) }}" required>
                @error('title')
                    <div class="text-red-400 text-sm mt-1">{{ $message }}</div>
                @enderror
            </div>

            <div>
                <label for="thumbnail" class="block text-sm font-medium text-gray-300 mb-1">Ảnh đại diện (Thumbnail)</label>
                <input type="file" name="thumbnail" id="thumbnail" 
                       class="block w-full text-sm text-gray-400
                              file:mr-4 file:py-2 file:px-4
                              file:rounded-full file:border-0
                              file:text-sm file:font-semibold
                              file:bg-blue-500 file:text-white
                              hover:file:bg-blue-600 cursor-pointer" 
                       accept="image/*">
                       
                {{-- Hiển thị ảnh hiện tại --}}
                @if ($news->thumbnail)
                    <div class="mt-4 border border-gray-600 rounded-lg overflow-hidden inline-block p-1 bg-gray-700">
                        <img src="{{ asset('storage/' . $news->thumbnail) }}" alt="Current Thumbnail" class="w-48 h-auto object-cover rounded-lg">
                    </div>
                @endif
                
                @error('thumbnail')
                    <div class="text-red-400 text-sm mt-1">{{ $message }}</div>
                @enderror
            </div>

            <div>
                <label for="tinymce-editor" class="block text-sm font-medium text-gray-300 mb-1">Nội dung</label>
                <textarea name="content" id="tinymce-editor" class="w-full @error('content') border-red-500 @enderror" required>{{ old('content', $news->content) }}</textarea>
                @error('content')
                    <div class="text-red-400 text-sm mt-1">{{ $message }}</div>
                @enderror
            </div>

            <div>
                <label for="is_active" class="block text-sm font-medium text-gray-300 mb-1">Trạng thái</label>
                <select name="is_active" id="is_active" 
                        class="w-full bg-gray-700 border border-gray-600 rounded-lg p-3 text-white focus:ring-blue-500 focus:border-blue-500">
                    <option value="1" {{ old('is_active', $news->is_active) == 1 ? 'selected' : '' }}>Hoạt động</option>
                    <option value="0" {{ old('is_active', $news->is_active) == 0 ? 'selected' : '' }}>Ẩn</option>
                </select>
            </div>

            <div class="flex justify-start gap-4 pt-4">
                <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-semibold px-6 py-2 rounded-lg transition-colors duration-300 flex items-center gap-2">
                    <i class="bi bi-save"></i> Cập nhật
                </button>
                <a href="{{ route('news.index') }}" class="bg-gray-600 hover:bg-gray-500 text-white font-semibold px-6 py-2 rounded-lg transition-colors duration-300">
                    Hủy
                </a>
            </div>

        </form>
    </div>
</div>

{{-- Giữ nguyên CDN và JS cho TinyMCE --}}
<script src="https://cdn.tiny.cloud/1/rmmh49b4qpvs6yg7r9ov3mmjtz8ltfutkp4hxyfguni1fzfz/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>

<script>
    tinymce.init({
        selector: '#tinymce-editor',
        height: 500,
        plugins: 'image link media table code lists advlist fullscreen',
        toolbar: 'undo redo | styles | bold italic underline | alignleft aligncenter alignright | forecolor backcolor | link image media table | numlist bullist | code fullscreen',
        content_style: 'body { font-family:Helvetica,Arial,sans-serif; font-size:14px; background-color: #1f2937; color: #d1d5db; }', // Dark mode cho nội dung editor
        
        setup: function(editor) {
            editor.on('change', function() {
                tinymce.activeEditor.save();
            });
        },
        images_upload_url: "{{ route('news.upload_images') }}",
        
        file_picker_types: 'image',
        file_picker_callback: function(callback, value, meta) {
            var input = document.createElement('input');
            input.setAttribute('type', 'file');
            input.setAttribute('accept', 'image/*');
            input.setAttribute('multiple', 'multiple');

            input.onchange = function() {
                var files = input.files;
                if (files.length > 0) {
                    var formData = new FormData();
                    for (let i = 0; i < files.length; i++) {
                        formData.append('images[]', files[i]);
                    }
                    // Thêm token CSRF
                    formData.append('_token', '{{ csrf_token() }}');

                    fetch("{{ route('news.upload_images') }}", { 
                        method: 'POST',
                        body: formData,
                    })
                    .then(response => response.json())
                    .then(data => {
                        data.images.forEach(image => {
                            callback(image.url, { alt: image.name });
                        });
                    })
                    .catch(error => {
                        console.error('Error uploading images:', error);
                    });
                }
            };

            input.click(); 
        }
    });

    document.querySelector('form').addEventListener('submit', function(event) {
        // Đảm bảo nội dung TinyMCE được lưu vào textarea trước khi submit
        tinymce.triggerSave(); 
        console.log('Form is being submitted');
    });
</script>

{{-- Xóa bỏ thẻ <style> cũ vì đã chuyển sang Tailwind class --}}
@endsection