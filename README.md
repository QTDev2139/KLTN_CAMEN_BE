## Auth
https://jwt-auth.readthedocs.io/en/develop/laravel-installation/
https://jwt-auth.readthedocs.io/en/develop/quick-start/

Middleware = Bộ chốt kiểm tra request/response trước khi đi tiếp.
🛡️ Một số middleware phổ biến trong Laravel:
Middleware	Chức năng
auth	    Kiểm tra người dùng đã đăng nhập hay chưa
guest	    Ngược lại: chỉ cho phép người chưa đăng nhập
verified	Kiểm tra email đã xác minh chưa
throttle	Giới hạn số lượng request (chống spam)
csrf	    Chống giả mạo request

Guard là gì?

Trong Laravel, guard là "người gác cổng" chịu trách nhiệm:
👉 Xác định "ai" là người đang đăng nhập và cách nào để xác thực họ.

🎯 Hiểu đơn giản:
Khi bạn gọi auth()->user() → Laravel cần biết:
“Tôi đang xác thực theo kiểu nào? Dùng driver nào? Provider nào?”
Chính guard sẽ quyết định điều đó.