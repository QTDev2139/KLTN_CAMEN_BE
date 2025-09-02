## Bảo mật Token

AccessToken => Nếu bị đánh cắp => Hacker khai thác dựa vào Token

-> Giải pháp: Hạ thấp thời gian sống của AccessToken -> Gây phiền phức cho người dùng

-> Cần bổ sung: refreshToken -> Thời gian sống lâu hơn -> Dùng để cấp lại AccessToken mới khi AccessToken cũ hết hạn (FE xử lý) 

-> Khi logout -> Thêm token vào Blacklist -> Khi authorization -> Cần kiểm tra token có trong blacklist
+ Tính hợp lệ
+ Thời gian sống
+ Có trong Blacklist hay không ?