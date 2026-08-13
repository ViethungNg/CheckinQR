# Quyết Định Thiết Kế & Quy Tắc Cá Nhân Hóa Dự Án (CheckinQR)

File này chứa các quy tắc cá nhân hóa và nguyên tắc thiết kế cho dự án **CheckinQR**. AI Agent sẽ tự động đọc và tuân thủ các quy tắc này trong mọi lần làm việc tiếp theo.

Xem chi tiết kiến trúc và danh sách toàn bộ chức năng tại: [SYSTEM_OVERVIEW.md](file:///c:/xampp/htdocs/CheckinQR/SYSTEM_OVERVIEW.md).

---

## 1. Cấu Hình Session & Hệ Thống
- **Thời gian Session mặc định**: 7 ngày ($604.800$ giây), được cấu hình tại [config/bootstrap.php](file:///c:/xampp/htdocs/CheckinQR/config/bootstrap.php).

---

## 2. Quy Tắc Giao Diện Dashboard (`admin/index.php`)
- **Mục đích**: Dashboard chỉ dùng để **theo dõi và giám sát số liệu tổng quan**, không đặt các thao tác/bảng biểu công việc chi tiết.
- **Thành phần Dashboard**:
  - 6 Thẻ chỉ số Pastel tổng quan.
  - 2 Biểu đồ tiến độ và trạng thái lấp đầy bàn tiệc.
  - **Không hiển thị**: Khung tìm kiếm khách hàng, bộ lọc chọn bàn và Bảng thống kê realtime ở trang Dashboard.

---

## 3. Quy Tắc Giao Diện Popup & Bảng Dữ Liệu (`admin/tables.php`)
- **Giới hạn hiển thị (Boundaries)**:
  - Khi mở Popup chi tiết bàn tiệc, Popup phải nằm gọn trong vùng hiển thị nội dung chính (`top: 70px`, `left: 265px`, `right: 20px`, `bottom: 20px`).
  - **Tuyệt đối không đè lên hay che phủ Sidebar bên trái và Header phía trên**.
- **Khóa cuộn trang ngoài**:
  - Khi Popup mở, áp dụng `overflow: hidden !important` lên `html`, `body` và `.main-content` để khóa cuộn trang web bên ngoài.
  - Nếu danh sách dài (50+ khách), chỉ cuộn duy nhất vùng chứa bảng dữ liệu bên trong Popup. Bấm nút **Đóng** hoặc gõ `Escape` để đóng Popup và mở lại cuộn trang ngoài.
- **Không sử dụng Icon**:
  - Không dùng icon SVG, FontAwesome, hay Emoji trong các Popup chi tiết (tiêu đề, nút bấm, các thẻ trạng thái,...).
  - Sử dụng văn bản thuần (Text-only) kết hợp màu nền/viền CSS hiện đại.
- **Tiêu đề & Bố cục**:
  - Tiêu đề tên bàn và phụ đề số liệu tổng quan luôn được căn giữa (`text-align: center`).
  - Bảng dữ liệu chi tiết phải có đủ 8 cột chuẩn: `Mã KH`, `Họ và Tên`, `SĐT`, `Đơn vị / Đại lý`, `Bàn ngồi`, `Mã trúng thưởng`, `Thời gian Checkin`, `Trạng thái`.

---

## 4. Biểu Đồ & Responsive Mobile
- **Căn tâm biểu đồ Donut**:
  - Tâm số liệu `% Tỷ lệ có mặt` phải luôn được tính toán động dựa theo tọa độ `chartArea` của Chart.js để nằm chính xác ở tâm lỗ rỗng biểu đồ trên cả Desktop và Mobile.
- **Vị trí Chú thích (Legend)**:
  - Nằm ở bên phải (`right`) trên màn hình lớn Desktop.
  - Tự động chuyển xuống bên dưới (`bottom`) trên màn hình di động Mobile ($\le 640\text{px}$).

---

## 5. Quy Tắc Viết Code & Typography (Coding Standards & Fonts)
- **Font Chữ Hệ Thống (Typography)**: Áp dụng font chữ **Google Sans** (`'Google Sans', 'Google Sans Flex', sans-serif`) làm font mặc định cho toàn bộ các màn hình giao diện (Check-in hiện trường, Dashboard, các bảng dữ liệu Admin, Popup).
- **Tương thích PHP**: Đảm bảo tương thích PHP 8.x, không dính lỗi cú pháp.
- **Định dạng bảng**: Dùng chuẩn thiết kế bảng Excel / Modern data table đồng bộ trên hệ thống Admin.
