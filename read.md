# Tổng Quan Ngôn Ngữ, Kiến Trúc & Quy Tắc Cá Nhân Hóa Hệ Thống CheckinQR

Tài liệu này tổng hợp toàn bộ **cấu trúc công nghệ**, **danh sách các chức năng hệ thống**, và **các quy tắc cá nhân hóa giao diện / trải nghiệm người dùng (UX/UI)** của dự án **CheckinQR**.

---

## 1. Tổng Quan Ngôn Ngữ & Công Nghệ (Tech Stack)

| Thành Phần | Công Nghệ Sử Dụng | Vai Trò / Chi Tiết |
| :--- | :--- | :--- |
| **Backend** | PHP 8.x (Vanilla PHP) | Xử lý logic hệ thống, phân quyền, API RESTful / SSE Realtime, Session |
| **Database** | MySQL / MariaDB (PDO) | Lưu trữ CSDL sự kiện, danh sách khách mời, bàn tiệc, lịch sử check-in |
| **Frontend** | HTML5 / CSS3 Vanilla | Cấu trúc chuẩn SEO, thiết kế Mobile-First, Responsive Desktop & Tablet |
| **Typography** | **Google Sans** | Font chữ mặc định đồng bộ trên toàn bộ hệ thống code & giao diện |
| **Styling System**| Modern CSS Design System | Sử dụng CSS Variables, màu pastel, glassmorphism, hỗ trợ **Dark Mode** (Giao diện tối) cho không gian sự kiện thiếu sáng |
| **Data Visualization** | Chart.js (v3/v4) | Vẽ biểu đồ tiến độ có mặt (Bar chart) & trạng thái lấp đầy bàn (Doughnut chart) |
| **PWA Engine** | Service Worker & Manifest | Hỗ trợ cài đặt ứng dụng Web App dạng PWA, cache tài nguyên, phản hồi rung (Haptic feedback) |

---

## 2. Cấu Hình Hệ Thống & Session

- **Session Lifetime**: Được thiết lập mặc định **7 ngày** (604.800 giây) trong `config/bootstrap.php`.
- **Session Store**: Được lưu riêng tại `storage/sessions` để đảm bảo tính riêng tư, tránh lỗi phân quyền hệ thống và tự động dọn dẹp (Garbage Collection).

---

## 3. Tổng Quan Các Chức Năng & Quy Tắc Thiết Kế UX/UI

### 3.1. Màn Hình Check-in QR (`index.php`)
- **Mục đích**: Giao diện tiếp đón hiện trường phục vụ quét mã QR hoặc tìm kiếm nhanh bằng SĐT/Tên.
- **Quy tắc UX/UI Hiện trường**:
  - **Phản hồi đa giác quan (Multi-sensory feedback)**: Khi quét QR thành công, thiết bị di động phải rung (Haptic) và phát âm thanh "Beep" ngắn.
  - **Phản hồi thị giác**: Toàn bộ viền/nền khu vực quét nháy viền Xanh lá (Hợp lệ), Vàng (Khách phát sinh/trùng lặp) hoặc Đỏ (Lỗi/Không tìm thấy) trong 0.5s để lễ tân nhận biết ngay qua khóe mắt.
  - **Thao tác 1 chạm**: Nút "Check-in khách phát sinh" được đặt to, rõ ở góc dưới màn hình (Floating Action Button) để dễ bấm bằng một tay.

### 3.2. Dashboard Quản Trị (`admin/index.php`)
- **Nguyên tắc thiết kế**: Giám sát thời gian thực, trực quan, hỗ trợ quyết định nhanh.
- **Thành phần**:
  - **6 Thẻ chỉ số Pastel (Skeleton Loading)**: Hiển thị hiệu ứng tải mượt mà trước khi có data. Bao gồm: Tổng khách, Đã tới, Bàn đầy, Bàn đông nhất, Chưa tới, Phát sinh.
  - **2 Biểu đồ Realtime (Có Drill-down)**:
    1. *Tiến độ có mặt theo bàn (Bar Chart)*: Tích hợp tooltip chi tiết. Click vào cột để mở danh sách khách của bàn đó.
    2. *Trạng thái lấp đầy bàn (Doughnut Chart)*: Căn giữa động 100% số liệu `% Tỷ lệ` ở tâm lỗ rỗng. Click vào các slice (Bàn đầy / Đang đón / Trống) để lọc nhanh danh sách.
- **Vị trí Chú thích (Legend)**:
  - Hiển thị bên phải (`right`) trên Desktop.
  - Tự động chuyển xuống bên dưới (`bottom`) trên Mobile (<= 640px).

### 3.3. Quản Lý Bàn Sự Kiện (`admin/tables.php`)
- **Sơ đồ bàn tiệc Realtime**: Hiển thị dạng lưới (Grid) các thẻ Card. Các thẻ tự động đổi màu nền (Background tint) theo tỷ lệ lấp đầy (Trắng -> Xanh nhạt -> Xanh đậm).
- **Popup Chi Tiết Bàn Tiệc (Full-Space Bounded Modal)**:
  - **Phạm vi hiển thị**: Phủ kín vùng không gian làm việc (`top: 70px`, `left: 265px`, `right: 20px`, `bottom: 20px`), tuyệt đối không đè lên Sidebar và Header để giữ bối cảnh điều hướng.
  - **Trải nghiệm cuộn (Scroll Lock)**: Khóa cuộn trang nền (`html.table-modal-active`). **Chỉ cuộn phần thân của Modal**.
  - **Bảng dữ liệu thông minh (Smart Data Table)**:
    - **Sticky Header**: Tiêu đề các cột trong bảng luôn ghim cố định ở trên cùng khi cuộn danh sách (giúp không bị nhầm lẫn cột).
    - **Tối ưu hiển thị cột (Responsive)**: Trên mobile, tự động ẩn cột `Mã KH` và `Thời gian Checkin` (có thể xem khi bấm mở rộng), ưu tiên giữ `Tên`, `Trạng thái` và `Bàn`.
    - **Sử dụng Icon Trực Quan (Semantic Icons)**: Thay vì dùng chữ thuần túy cho trạng thái, sử dụng Badges màu pastel kèm Icon (VD: 🟢 *Check, text màu lục* cho "Đã tới"; 🟡 *Clock, text màu cam* cho "Chưa tới"). Việc này giúp mắt quét (scan) thông tin danh sách nhanh gấp 3 lần so với việc đọc chữ.

### 3.4. Quản Lý Danh Sách Khách Hàng (`admin/guests.php`)
- **Tính năng**: Thêm/Sửa/Xóa khách mời, xếp bàn, cấp mã QR, xuất/nhập Excel.
- **UX Cải tiến**: Bổ sung thanh tìm kiếm Realtime (lọc kết quả ngay khi gõ không cần bấm Enter - 0ms DOM filtering) và bộ lọc nhanh theo "Trạng thái".

### 3.5. Quản Lý Lịch Sử Check-in (`admin/checkins.php`)
- **Tính năng**: Stream dữ liệu realtime qua Server-Sent Events (SSE). 
- **UX Cải tiến**: Khi có khách mới check-in, dòng dữ liệu mới sẽ xuất hiện ở trên cùng bảng với hiệu ứng *Highlight Fade-out* (sáng lên màu vàng nhạt rồi mờ dần về trắng trong 2 giây) để thu hút sự chú ý của người quản trị.

### 3.6. Quản Lý Sự Kiện & Tài Khoản (`admin/events.php`, `admin/users.php`)
- **Phân quyền vai trò rõ ràng (Role-based UI)**:
  - `admin`: Hiển thị toàn bộ menu và nút thao tác (Thêm/Sửa/Xóa).
  - `kinh_doanh`: Chỉ thấy dữ liệu bàn/khách của mình, các nút Xóa/Sửa quan trọng tự động bị ẩn hoặc chuyển sang trạng thái *Disabled* (Làm xám) kèm tooltip giải thích.
  - `staff`: Tự động chuyển hướng (Redirect) thẳng vào màn hình quét QR, ẩn hoàn toàn Sidebar quản trị phức tạp.

---

## 4. Nguyên Tắc Phát Triển Code (Coding Standards)

1. **Chuẩn PHP 8.x**: Strict typing, kiểm tra lỗi cú pháp (`php -l`) trước khi deploy.
2. **Thiết Kế Tối Ưu Tốc Độ (Performance-First UX)**: 
   - Không sử dụng các thư viện UI quá nặng (như jQuery UI). Ưu tiên thao tác DOM trực tiếp bằng Vanilla JS.
   - Các hiệu ứng chuyển động (Animations) chỉ dùng thuộc tính `transform` và `opacity` để đạt 60 FPS, không gây giật lag.
3. **Bảo Mật & An Toàn Dữ Liệu**: Xác thực Session liên tục, tích hợp CSRF Token cho mọi form, chống Session Fixation.