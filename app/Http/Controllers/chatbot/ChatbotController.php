<?php

namespace App\Http\Controllers\chatbot;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Category;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class ChatbotController extends Controller
{
    public function ask(Request $request)
    {
        set_time_limit(60); 

        try {
            $userMessage = $request->input('message');
            $sessionId = $request->input('session_id') ?? session()->getId();
            
            $historyKey = "chat_history_{$sessionId}";
            $lastProductKey = "chat_last_product_{$sessionId}";
            $apiKey = env('GEMINI_API_KEY');

            if (!$apiKey) return response()->json(['reply' => 'Lỗi cấu hình API Key.']);

            // =================================================================
            // 1. TỪ ĐIỂN DỊCH MÀU (DB Tiếng Anh -> Hiển thị Tiếng Việt)
            // =================================================================
            $colorMap = [
                'black'          => 'Đen',
                'white'          => 'Trắng',
                'red'            => 'Đỏ',
                'blue'           => 'Xanh dương',
                'green'          => 'Xanh lá',
                'yellow'         => 'Vàng',
                'purple'         => 'Tím',
                'pink'           => 'Hồng',
                'gold'           => 'Vàng kim',
                'silver'         => 'Bạc',
                'gray'           => 'Xám',
                'orange'         => 'Cam',
                'titanium'       => 'Titan',
                'black titanium' => 'Titan Đen',
                'natural titanium'=> 'Titan Tự Nhiên',
                'blue titanium'  => 'Titan Xanh',
                'white titanium' => 'Titan Trắng',
                'desert titanium'=> 'Titan Sa Mạc',
                'deep purple'    => 'Tím Đậm',
                'space black'    => 'Đen Không Gian',
            ];

            // 2. Chuẩn hóa tin nhắn
            $lowerMsg = Str::lower($userMessage);
            $productContext = "";
            $isSpecialRequest = false;

            // =================================================================
            // 3. XỬ LÝ "BÁN CHẠY" / "XEM NHIỀU"
            // =================================================================
            if (Str::contains($lowerMsg, ['bán chạy', 'mua nhiều', 'hot', 'đắt hàng', 'ưa chuộng'])) {
                $isSpecialRequest = true;
                $topProducts = Product::where('status', 'active')->orderBy('sold', 'desc')->take(5)->with('variants')->get();
                if ($topProducts->isNotEmpty()) {
                    $productContext = "DANH SÁCH BÁN CHẠY:\n";
                    foreach ($topProducts as $i => $p) {
                        $price = $p->variants->first() ? number_format($p->variants->first()->price / 1000000, 1) . 'tr' : 'LH';
                        $productContext .= "#" . ($i+1) . ". {$p->name} (Đã bán: " . ($p->sold ?? 0) . ", Giá: $price)\n";
                    }
                }
            } elseif (Str::contains($lowerMsg, ['xem nhiều', 'quan tâm', 'lượt xem'])) {
                $isSpecialRequest = true;
                $topProducts = Product::where('status', 'active')->orderBy('view', 'desc')->take(5)->with('variants')->get();
                if ($topProducts->isNotEmpty()) {
                    $productContext = "DANH SÁCH XEM NHIỀU:\n";
                    foreach ($topProducts as $i => $p) {
                        $productContext .= "#" . ($i+1) . ". {$p->name} (Lượt xem: " . ($p->view ?? 0) . ")\n";
                    }
                }
            }

            // =================================================================
            // 4. TÌM KIẾM THÔNG MINH (TÁCH MÀU KHỎI TÊN)
            // =================================================================
            if (!$isSpecialRequest) {
                // 4.1. Lọc từ thừa
                $stopWords = ['tôi', 'muốn', 'mua', 'có', 'không', 'giá', 'bao', 'nhiêu', 'màu', 'gì', 'còn', 'chiếc', 'cái', 'shop', 'ad', 'ạ', 'ơi', 'nhé', 'thế', 'vậy', 'là', 'nào'];
                $cleanMsg = preg_replace('/[^\p{L}\p{N}\s]/u', '', $lowerMsg);
                foreach ($stopWords as $word) {
                    $cleanMsg = str_replace(' ' . $word . ' ', ' ', ' ' . $cleanMsg . ' ');
                }
                $searchKeyword = trim($cleanMsg); 

                // 4.2. Tách từ khóa màu sắc khỏi tên sản phẩm
                // Ví dụ: "iphone 16 đen" -> Tách "đen" ra -> Còn "iphone 16" để tìm trong DB
                $colorsVN = ['đen', 'trắng', 'đỏ', 'xanh', 'vàng', 'tím', 'hồng', 'cam', 'bạc', 'xám', 'titan', 'ngọc', 'lam', 'lục'];
                $nameKeyword = $searchKeyword;
                foreach ($colorsVN as $c) {
                    $nameKeyword = str_replace($c, '', $nameKeyword);
                }
                $nameKeyword = trim($nameKeyword); // Đây là tên sản phẩm sạch (vd: "iphone 16")

                // 4.3. Tìm sản phẩm theo tên đã làm sạch
                $productIds = Product::where('status', 'active')
                    ->where('name', 'LIKE', "%{$nameKeyword}%")
                    ->pluck('id');

                // Fallback: Nếu tên sạch quá ngắn hoặc không tìm thấy, thử dùng lại sản phẩm cũ
                if ($productIds->isEmpty() && (empty($nameKeyword) || strlen($nameKeyword) < 2)) {
                    $lastProduct = Cache::get($lastProductKey);
                    if ($lastProduct) {
                        $productIds = Product::where('status', 'active')
                            ->where('name', 'LIKE', "%{$lastProduct}%")
                            ->pluck('id');
                    }
                }

                // 4.4. Lấy dữ liệu biến thể (bao gồm cả sản phẩm không có variant)
                $productVariants = ProductVariant::query()
                    ->where('status', 'active')
                    ->whereIn('product_id', $productIds)
                    ->with('product:id,name,have_variant')
                    ->take(50)
                    ->get();

                // Lấy thêm thông tin sản phẩm để kiểm tra have_variant
                $products = Product::whereIn('id', $productIds)
                    ->where('status', 'active')
                    ->with(['variants' => function($q) {
                        $q->where('status', 'active');
                    }])
                    ->get();

                if ($productVariants->isNotEmpty() || $products->isNotEmpty()) {
                    // Lưu cache tên sản phẩm chính xác vừa tìm được
                    if ($productVariants->isNotEmpty()) {
                        $foundName = $productVariants->first()->product->name;
                    } else {
                        $foundName = $products->first()->name;
                    }
                    Cache::put($lastProductKey, $foundName, 1800); 

                    $productContext = "DỮ LIỆU KHO HÀNG:\n";
                    $grouped = $productVariants->groupBy('product.name');
                    
                    // Chuẩn bị suggestions để trả về cho frontend (image + link + price)
                    $suggestions = [];
                    $addedProductIds = [];
                    
                    // Xử lý từng sản phẩm
                    foreach ($products as $product) {
                        $pName = $product->name;
                        $variants = $product->variants;
                        
                        if ($variants->isEmpty()) continue;
                        
                        $productContext .= "Sản phẩm: {$pName}\n";
                        
                        // Nếu sản phẩm không có variant (have_variant = 0)
                        if ($product->have_variant == 0) {
                            $variant = $variants->first(); // Chỉ có 1 variant với color/storage = null
                            if ($variant) {
                                $price = number_format($variant->price, 0, ',', '.') . '₫';
                                $originPrice = $variant->origin_price ? number_format($variant->origin_price, 0, ',', '.') . '₫' : '';
                                $stock = $variant->stock;
                                $productContext .= " - Giá: $price";
                                if ($originPrice && $variant->origin_price > $variant->price) {
                                    $productContext .= " (Giá gốc: $originPrice)";
                                }
                                $productContext .= " - Kho: $stock sản phẩm\n";
                            }
                        } else {
                            // Sản phẩm có variant - nhóm theo màu
                            $byColor = $variants->groupBy('color');
                            foreach ($byColor as $color => $items) {
                                // Dịch màu sang tiếng Việt
                                $keyColor = Str::lower($color ?? '');
                                $vnColor = !empty($color) ? ($colorMap[$keyColor] ?? $color) : 'Không xác định'; 

                                $info = [];
                                foreach($items as $item) {
                                    $price = number_format($item->price / 1000, 0, ',', '.') . '₫';
                                    $originPrice = $item->origin_price ? number_format($item->origin_price / 1000, 0, ',', '.') . '₫' : '';
                                    $stock = $item->stock;
                                    $storage = $item->storage ?? 'N/A';
                                    $priceText = "$storage: $price";
                                    if ($originPrice && $item->origin_price > $item->price) {
                                        $priceText .= " (Giá gốc: $originPrice)";
                                    }
                                    $priceText .= " - Kho: $stock";
                                    $info[] = $priceText;
                                }
                                $productContext .= " - Màu $vnColor: " . implode(', ', $info) . "\n";
                            }
                        }
                        
                        // Thêm 1 suggestion cho mỗi sản phẩm (lấy variant đầu tiên)
                        $firstVariant = $variants->first();
                        if ($firstVariant && !in_array($product->id, $addedProductIds)) {
                            $prod = $product;
                            if (true) {
                                        $img = '';
                                        // Lấy path ảnh từ product.image hoặc product.images()->first()->path
                                        $candidate = null;
                                        if (!empty($prod->image)) $candidate = $prod->image;
                                        if (empty($candidate)) {
                                            $pi = $prod->images()->first();
                                            if ($pi) {
                                                // product_images table may use different column names
                                                $candidate = $pi->path ?? $pi->image ?? $pi->mini_image ?? null;
                                            }
                                        }

                                        // Helper: chuyển path thành URL hợp lệ
                                        if (!empty($candidate)) {
                                            // Nếu đã là URL tuyệt đối, dùng trực tiếp
                                            if (preg_match('/^https?:\/\//i', $candidate)) {
                                                $img = $candidate;
                                            } else {
                                                // Chuẩn hóa: loại bỏ dấu / đầu nếu có
                                                $norm = ltrim($candidate, '/\\');

                                                // Trường hợp file nằm trong public/uploads (vd: uploads/products/....)
                                                if (stripos($norm, 'uploads/') === 0 || stripos($norm, 'uploads\\') === 0) {
                                                    // Tạo URL đầy đủ dựa trên host hiện tại
                                                    $host = request()->getSchemeAndHttpHost();
                                                    $img = $host . '/' . str_replace('\\', '/', $norm);
                                                }
                                                // Nếu đường dẫn bắt đầu bằng storage/ (ví dụ storage/products/..)
                                                elseif (stripos($norm, 'storage/') === 0) {
                                                    try {
                                                        $img = Storage::url($norm);
                                                    } catch (\Exception $e) {
                                                        $img = url($norm);
                                                    }
                                                }
                                                else {
                                                    // Nếu file tồn tại trong public, dùng asset()
                                                    if (file_exists(public_path($norm))) {
                                                        $img = asset($norm);
                                                    } else {
                                                        // Thử Storage::url() rồi fallback
                                                        try {
                                                            $maybe = Storage::url($norm);
                                                            if ($maybe && preg_match('/^https?:\/\//i', $maybe)) $img = $maybe;
                                                            else $img = asset($norm);
                                                        } catch (\Exception $e) {
                                                            $img = asset($norm);
                                                        }
                                                    }
                                                }
                                            }
                                        }

                                        // Nếu vẫn rỗng, dùng ảnh placeholder (inline SVG data URI)
                                        if (empty($img)) {
                                            $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200" viewBox="0 0 200 200"><rect width="100%" height="100%" fill="#f3f4f6"/><text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" fill="#9ca3af" font-family="Arial, sans-serif" font-size="16">No Image</text></svg>';
                                            $img = 'data:image/svg+xml;utf8,' . rawurlencode($svg);
                                        }

                                $url = route(auth()->check() ? 'customer.product_detail' : 'guest.product_detail', $prod->id);
                                $suggestions[] = [
                                    'id' => $prod->id,
                                    'name' => $prod->name,
                                    'image' => $img,
                                    'price' => $firstVariant->price ?? $prod->sale_price ?? null,
                                    'url' => $url,
                                ];
                                $addedProductIds[] = $prod->id;
                            }
                        }
                    }
                    
                    // Xử lý suggestions cho sản phẩm không có variant (nếu chưa được thêm)
                    foreach ($products as $product) {
                        if ($product->have_variant == 0 && !in_array($product->id, $addedProductIds)) {
                            $variant = $product->variants->first();
                            if ($variant) {
                                // Lấy ảnh sản phẩm
                                $img = '';
                                $candidate = $product->image;
                                if (empty($candidate)) {
                                    $pi = $product->images()->first();
                                    if ($pi) {
                                        $candidate = $pi->path ?? $pi->image ?? $pi->mini_image ?? null;
                                    }
                                }
                                if (!empty($candidate)) {
                                    if (preg_match('/^https?:\/\//i', $candidate)) {
                                        $img = $candidate;
                                    } else {
                                        $norm = ltrim($candidate, '/\\');
                                        if (stripos($norm, 'uploads/') === 0 || stripos($norm, 'uploads\\') === 0) {
                                            $host = request()->getSchemeAndHttpHost();
                                            $img = $host . '/' . str_replace('\\', '/', $norm);
                                        } elseif (stripos($norm, 'storage/') === 0) {
                                            try {
                                                $img = Storage::url($norm);
                                            } catch (\Exception $e) {
                                                $img = url($norm);
                                            }
                                        } else {
                                            if (file_exists(public_path($norm))) {
                                                $img = asset($norm);
                                            } else {
                                                try {
                                                    $maybe = Storage::url($norm);
                                                    if ($maybe && preg_match('/^https?:\/\//i', $maybe)) $img = $maybe;
                                                    else $img = asset($norm);
                                                } catch (\Exception $e) {
                                                    $img = asset($norm);
                                                }
                                            }
                                        }
                                    }
                                }
                                if (empty($img)) {
                                    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200" viewBox="0 0 200 200"><rect width="100%" height="100%" fill="#f3f4f6"/><text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" fill="#9ca3af" font-family="Arial, sans-serif" font-size="16">No Image</text></svg>';
                                    $img = 'data:image/svg+xml;utf8,' . rawurlencode($svg);
                                }
                                
                                $url = route(auth()->check() ? 'customer.product_detail' : 'guest.product_detail', $product->id);
                                $suggestions[] = [
                                    'id' => $product->id,
                                    'name' => $product->name,
                                    'image' => $img,
                                    'price' => $variant->price ?? $product->sale_price ?? null,
                                    'url' => $url,
                                ];
                                $addedProductIds[] = $product->id;
                            }
                        }
                    }
                } else {
                    $productContext = "KHÔNG TÌM THẤY sản phẩm nào khớp với từ khóa '{$nameKeyword}'.\n";
                    $random = Product::where('status', 'active')->inRandomOrder()->take(3)->pluck('name')->toArray();
                    if($random) $productContext .= "Gợi ý: " . implode(', ', $random);
                }
            }

            // =================================================================
            // 5. GỌI GEMINI API
            // =================================================================
            $history = Cache::get($historyKey, []);
            
            $systemPrompt = "Bạn là nhân viên tư vấn NTPhone, chuyên bán điện thoại và phụ kiện.\n" .
                "Dựa vào DỮ LIỆU KHO HÀNG dưới đây để trả lời chính xác:\n\n" .
                "HƯỚNG DẪN TRẢ LỜI:\n" .
                "1. Khi khách hỏi về GIÁ:\n" .
                "   - Trả lời rõ ràng giá hiện tại (giá sau giảm)\n" .
                "   - Nếu có giá gốc cao hơn, hãy đề cập để khách biết được ưu đãi\n" .
                "   - Nếu sản phẩm có nhiều phiên bản (màu/dung lượng), liệt kê tất cả các mức giá\n" .
                "   - Nếu sản phẩm không có variant, chỉ cần báo giá duy nhất\n" .
                "2. Khi khách hỏi về MÀU:\n" .
                "   - Kiểm tra xem trong dữ liệu có màu nào (ví dụ: 'Màu Đen', 'Màu Titan Đen')\n" .
                "   - Liệt kê tất cả các màu có sẵn\n" .
                "3. Khi khách hỏi về KHO:\n" .
                "   - Báo số lượng tồn kho chính xác\n" .
                "   - Nếu hết hàng, thông báo rõ ràng\n" .
                "4. Luôn dùng tiếng Việt tự nhiên, thân thiện, chuyên nghiệp.\n" .
                "5. Nếu khách chỉ hỏi tên sản phẩm, hãy cung cấp đầy đủ thông tin: giá, màu, dung lượng, kho hàng.\n\n" .
                "DỮ LIỆU KHO HÀNG:\n" .
                $productContext;

            $contents = [
                ["role" => "user", "parts" => [["text" => $systemPrompt]]]
            ];
            foreach ($history as $msg) $contents[] = $msg;
            $contents[] = ["role" => "user", "parts" => [["text" => $userMessage]]];

            $response = Http::timeout(60)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={$apiKey}", [
                    "contents" => $contents,
                    "generationConfig" => ["temperature" => 0.4, "maxOutputTokens" => 1000]
                ]);

            if ($response->successful()) {
                $reply = $response->json()['candidates'][0]['content']['parts'][0]['text'] ?? 'Em chưa rõ câu hỏi ạ.';

                $history[] = ["role" => "user", "parts" => [["text" => $userMessage]]];
                $history[] = ["role" => "model", "parts" => [["text" => $reply]]];
                if(count($history) > 10) $history = array_slice($history, -10); 
                Cache::put($historyKey, $history, 3600);

                // Trả về cả reply text và suggestions (nếu có)
                $payload = ['reply' => $reply, 'session_id' => $sessionId];
                if (!empty($suggestions)) {
                    $payload['suggestions'] = array_values(array_slice($suggestions, 0, 5));
                }

                return response()->json($payload);
            } else {
                return response()->json(['reply' => 'Hệ thống đang quá tải, anh/chị đợi chút nhé!']);
            }

        } catch (\Exception $e) {
            Log::error('Bot Error: ' . $e->getMessage());
            return response()->json(['reply' => 'Em đang gặp chút trục trặc, anh/chị hãy liên hệ với Admin nhé!']);
        }
    }
}