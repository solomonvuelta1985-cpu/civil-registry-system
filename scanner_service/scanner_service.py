"""
iScan Scanner Service for DS-530 II
Simple local service to enable document scanning from the browser
Compatible with Epson DS-530 II scanner
"""

from flask import Flask, request, jsonify, send_file
from flask_cors import CORS
import io
import tempfile
import os
from datetime import datetime

try:
    import sane
    SANE_AVAILABLE = True
except ImportError:
    SANE_AVAILABLE = False
    print("WARNING: python-sane not installed. Scanner functionality will be simulated.")

app = Flask(__name__)
CORS(app)  # Enable CORS for browser access

# Scanner configuration
SCANNER_MODEL = "DS-530"
SCANNER_DPI = 300
SCANNER_MODE = "Color"

def get_scanner_device():
    """Get the Epson DS-530 II scanner device"""
    if not SANE_AVAILABLE:
        return None

    try:
        sane.init()
        devices = sane.get_devices()

        # Find DS-530 scanner
        for device in devices:
            if SCANNER_MODEL in device[1] or SCANNER_MODEL in device[2]:
                return sane.open(device[0])

        return None
    except Exception as e:
        print(f"Error initializing scanner: {e}")
        return None

def scan_to_pdf(scanner_device, quality='high', color_mode='color', resolution=300):
    """Scan document and convert to PDF"""
    try:
        if not scanner_device and SANE_AVAILABLE:
            raise Exception("Scanner not available")

        # Configure scanner settings
        if scanner_device:
            scanner_device.mode = color_mode if color_mode != 'color' else 'Color'
            scanner_device.resolution = resolution

            # Scan the document
            scanner_device.start()
            image = scanner_device.snap()
        else:
            # Simulation mode - create a blank PDF for testing
            from reportlab.pdfgen import canvas
            from reportlab.lib.pagesizes import letter

            buffer = io.BytesIO()
            c = canvas.Canvas(buffer, pagesize=letter)
            c.drawString(100, 750, "SIMULATED SCAN - DS-530 II")
            c.drawString(100, 730, f"Timestamp: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
            c.drawString(100, 710, "Install python-sane for actual scanning")
            c.save()
            buffer.seek(0)
            return buffer

        # Convert image to PDF
        from PIL import Image
        import img2pdf

        # Save image to temporary file
        temp_image = tempfile.NamedTemporaryFile(delete=False, suffix='.png')
        image.save(temp_image.name, 'PNG')
        temp_image.close()

        # Convert to PDF
        pdf_bytes = img2pdf.convert(temp_image.name)

        # Clean up temporary image
        os.unlink(temp_image.name)

        return io.BytesIO(pdf_bytes)

    except Exception as e:
        raise Exception(f"Scanning failed: {str(e)}")

@app.route('/scanner/status', methods=['GET'])
def scanner_status():
    """Check if scanner is available and ready"""
    try:
        scanner = get_scanner_device()

        if scanner or not SANE_AVAILABLE:
            return jsonify({
                'available': True,
                'model': 'Epson DS-530 II',
                'status': 'ready',
                'simulation': not SANE_AVAILABLE
            })
        else:
            return jsonify({
                'available': False,
                'model': None,
                'status': 'not_found',
                'message': 'DS-530 II scanner not detected'
            }), 404

    except Exception as e:
        return jsonify({
            'available': False,
            'error': str(e)
        }), 500

@app.route('/scanner/scan', methods=['POST'])
def scan_document():
    """Perform document scan and return PDF"""
    try:
        # Get scan parameters
        data = request.get_json() or {}
        quality = data.get('quality', 'high')
        color_mode = data.get('colorMode', 'color')
        resolution = data.get('resolution', 300)

        # Get scanner
        scanner = get_scanner_device()

        # Perform scan
        pdf_buffer = scan_to_pdf(scanner, quality, color_mode, resolution)

        # Generate filename
        filename = f"scanned_{datetime.now().strftime('%Y%m%d_%H%M%S')}.pdf"

        # Return PDF file
        return send_file(
            pdf_buffer,
            mimetype='application/pdf',
            as_attachment=True,
            download_name=filename
        )

    except Exception as e:
        return jsonify({
            'success': False,
            'error': str(e)
        }), 500

@app.route('/scanner/test', methods=['GET'])
def test_scanner():
    """Test endpoint to verify service is running"""
    return jsonify({
        'service': 'iScan Scanner Service',
        'version': '1.0',
        'status': 'running',
        'scanner_model': 'Epson DS-530 II',
        'port': 18622,
        'sane_available': SANE_AVAILABLE
    })

if __name__ == '__main__':
    print("=" * 60)
    print("iScan Scanner Service - DS-530 II")
    print("=" * 60)
    print(f"SANE Library: {'Available' if SANE_AVAILABLE else 'Not installed (simulation mode)'}")
    print(f"Service running on: http://localhost:18622")
    print(f"Status endpoint: http://localhost:18622/scanner/status")
    print(f"Test endpoint: http://localhost:18622/scanner/test")
    print("=" * 60)
    print("\nPress Ctrl+C to stop the service\n")

    app.run(host='localhost', port=18622, debug=False)
