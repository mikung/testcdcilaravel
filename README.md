# คู่มือการติดตั้งและรันโปรเจกต์ Laravel API ด้วย Docker

> คู่มือนี้เขียนสำหรับผู้เริ่มต้น อ่านทีละขั้นตอนและทำตามได้เลย

---

## สิ่งที่ต้องติดตั้งก่อน

| โปรแกรม | ดาวน์โหลดจาก | วิธีเช็คว่ามีแล้ว |
|---|---|---|
| **Docker Desktop** | https://www.docker.com/products/docker-desktop | เปิดโปรแกรม Docker Desktop ขึ้นมาได้ |
| **Git** | https://git-scm.com/downloads | พิมพ์ `git --version` ใน Terminal |

> **หมายเหตุ:** ไม่ต้องติดตั้ง PHP หรือ MySQL บนเครื่องเอง Docker จัดการให้ทั้งหมด

---

## ขั้นตอนการติดตั้ง (ทำครั้งแรกครั้งเดียว)

### ขั้นที่ 1 — ดาวน์โหลดโค้ด

เปิด Terminal (หรือ Command Prompt) แล้วพิมพ์:

```bash
git clone <URL ของ repository>
cd laravel-api
```

> แทนที่ `<URL ของ repository>` ด้วย URL จริงที่ได้รับมา

---

### ขั้นที่ 2 — สร้างไฟล์ตั้งค่า

โปรเจกต์ต้องมีไฟล์ชื่อ `.env` สำหรับเก็บค่าต่าง ๆ เช่น รหัสผ่านฐานข้อมูล

```bash
cp .env.example .env
```

> คำสั่งนี้แค่ copy ไฟล์ตัวอย่างมาใช้ ยังไม่ต้องแก้ไขอะไร

---

### ขั้นที่ 3 — เปิด Docker Desktop

1. เปิดโปรแกรม **Docker Desktop** บนเครื่อง
2. รอจนไอคอน Docker ด้านล่างขวาของจอ **ไม่มีวงกลมหมุน** (แสดงว่า Docker พร้อมใช้งาน)

---

### ขั้นที่ 4 — Build และรันโปรเจกต์

```bash
docker compose up -d --build
```

คำสั่งนี้จะ:
- ดาวน์โหลด PHP, Nginx, MySQL ให้อัตโนมัติ
- ติดตั้ง package ของโปรเจกต์
- รันทุกอย่างอยู่เบื้องหลัง

> ครั้งแรกอาจใช้เวลา **5-15 นาที** ขึ้นอยู่กับความเร็วอินเทอร์เน็ต

---

### ขั้นที่ 5 — สร้าง APP KEY

```bash
docker compose exec app php artisan key:generate
```

> ทำแค่ครั้งแรกครั้งเดียว เป็นการสร้างรหัสลับสำหรับโปรแกรม

---

### ขั้นที่ 6 — สร้างตารางฐานข้อมูล

```bash
docker compose exec app php artisan migrate
```

> คำสั่งนี้สร้างตารางในฐานข้อมูลให้ครบ

---

### ขั้นที่ 7 — ทดสอบว่าใช้งานได้

เปิด Browser แล้วไปที่:

```
http://localhost:8080
```

ถ้าเห็นหน้า Laravel แสดงว่า **ติดตั้งสำเร็จ!**

---

## การรันโปรเจกต์ (ครั้งต่อไป)

ครั้งต่อไปทำแค่ 2 ขั้นตอน:

```bash
# 1. เปิด Docker Desktop ก่อน (รอให้พร้อม)

# 2. รันโปรเจกต์
docker compose up -d
```

---

## การหยุดโปรเจกต์

```bash
docker compose down
```

> ข้อมูลในฐานข้อมูลจะยังอยู่ครบ ไม่หายไปไหน

---

## คำสั่งที่ใช้บ่อย

| ต้องการทำอะไร | คำสั่ง |
|---|---|
| เริ่มรันโปรเจกต์ | `docker compose up -d` |
| หยุดโปรเจกต์ | `docker compose down` |
| ดู log ของโปรเจกต์ | `docker compose logs -f` |
| รัน migration ใหม่ | `docker compose exec app php artisan migrate` |
| เข้าไปใน container | `docker compose exec app bash` |
| ดูสถานะ container | `docker compose ps` |

---

## โครงสร้างที่รันอยู่

```
Browser (http://localhost:8080)
        |
        v
  [ Nginx :80 ]        <-- รับ request จาก Browser
        |
        v
  [ PHP-FPM :9000 ]    <-- ประมวลผล Laravel
        |
        v
  [ MySQL :3306 ]      <-- เก็บข้อมูล
```

---

## แก้ปัญหาที่พบบ่อย

### ปัญหา: Docker ดาวน์โหลด image ไม่ได้ (timeout)

เกิดจากเน็ตเชื่อมต่อ Docker Hub ไม่ได้ แก้ได้ 2 วิธี:

**วิธีที่ 1 — ตั้ง Mirror (แนะนำ)**

1. เปิด Docker Desktop
2. ไปที่ **Settings** (ไอคอนฟันเฟือง)
3. เลือก **Docker Engine**
4. แก้ไข JSON เพิ่ม registry-mirrors:

```json
{
  "registry-mirrors": [
    "https://mirror.gcr.io"
  ]
}
```

5. กด **Apply & Restart**
6. รัน `docker compose up -d --build` ใหม่

**วิธีที่ 2 — เปิด VPN แล้วลองใหม่**

---

### ปัญหา: port 8080 ถูกใช้งานอยู่แล้ว

แก้ไขไฟล์ `docker-compose.yml` บรรทัด:
```yaml
ports:
  - "8080:80"
```
เปลี่ยน `8080` เป็นเลขอื่น เช่น `8090:80` แล้วรันใหม่

---

### ปัญหา: หน้าเว็บขึ้น error หลัง migrate

```bash
docker compose exec app php artisan config:clear
docker compose exec app php artisan cache:clear
```

---

### ปัญหา: ต้องการล้างทุกอย่างแล้วเริ่มใหม่

```bash
docker compose down -v
docker compose up -d --build
```

> `-v` จะลบข้อมูลในฐานข้อมูลด้วย ระวังถ้ามีข้อมูลสำคัญ

---

## ข้อมูลการเชื่อมต่อฐานข้อมูล (สำหรับโปรแกรม Database Client)

| | ค่า |
|---|---|
| Host | `localhost` |
| Port | `3306` |
| Database | `laravel_db` |
| Username | `laravel_user` |
| Password | `secret` |
