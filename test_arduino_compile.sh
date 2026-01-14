#!/bin/bash

# =====================================================
# TEST SCRIPT: Verify Arduino Code Compilation
# =====================================================

echo "==================================="
echo "Arduino Code Compilation Test"
echo "==================================="
echo ""

# Check if arduino-cli is installed
if ! command -v arduino-cli &> /dev/null; then
    echo "❌ arduino-cli not found!"
    echo "Please install arduino-cli first:"
    echo "  https://arduino.github.io/arduino-cli/latest/installation/"
    exit 1
fi

echo "✓ arduino-cli found"
echo ""

# Set variables
SKETCH_PATH="codearduino.ino"
BOARD_FQBN="esp32:esp32:esp32"

# Check if sketch exists
if [ ! -f "$SKETCH_PATH" ]; then
    echo "❌ Sketch file not found: $SKETCH_PATH"
    exit 1
fi

echo "✓ Sketch file found: $SKETCH_PATH"
echo ""

# Install ESP32 board if not already installed
echo "📦 Checking ESP32 board installation..."
arduino-cli core update-index
arduino-cli core install esp32:esp32

echo ""
echo "📚 Installing required libraries..."
arduino-cli lib install "Adafruit GFX Library"
arduino-cli lib install "Adafruit SSD1306"
arduino-cli lib install "Adafruit Fingerprint Sensor Library"
arduino-cli lib install "ArduinoJson"
arduino-cli lib install "WiFiManager"

echo ""
echo "🔨 Compiling sketch..."
echo "-----------------------------------"

if arduino-cli compile --fqbn $BOARD_FQBN $SKETCH_PATH; then
    echo ""
    echo "✅ COMPILATION SUCCESSFUL!"
    echo ""
    echo "Sketch size information:"
    arduino-cli compile --fqbn $BOARD_FQBN $SKETCH_PATH --verbose | grep "Sketch uses"
    echo ""
    echo "Next steps:"
    echo "1. Connect your ESP32 board"
    echo "2. Find the port: arduino-cli board list"
    echo "3. Upload: arduino-cli upload -p <PORT> --fqbn $BOARD_FQBN $SKETCH_PATH"
else
    echo ""
    echo "❌ COMPILATION FAILED!"
    echo "Please check the error messages above."
    exit 1
fi
