"""
iScan Scanner Service for DS-530 II
Simple local service to enable document scanning from the browser
Compatible with Epson DS-530 II scanner
"""

from flask import Flask, request, jsonify, send_file, abort
import io
import tempfile
import os
import threading
from datetime import datetime

try:
    import sane
    SANE_AVAILABLE = True
except ImportError:
    SANE_AVAILABLE = False
    print("WARNING: python-sane not installed. Scanner functionality will be simulated.")

app = Flask(__name__)
ALLOWED_ORIGINS = {origin.strip() for origin in os.environ.get(
    'ISCAN_ALLOWED_ORIGINS', 'http://localhost:80,http://127.0.0.1:80'
).split(',') if origin.strip()}
SCANNER_TOKEN = os.environ.get('ISCAN_SCANNER_TOKEN', '')
SIMULATION_ENABLED = os.environ.get('ISCAN_SCANNER_SIMULATION', 'false').lower() == 'true'
scan_lock = threading.Lock()

@app.before_request
def enforce_origin_and_auth():
    origin = request.headers.get('Origin')
    if origin and origin not in ALLOWED_ORIGINS:
        return jsonify({'success': False, 'error': 'Origin not allowed'}), 403
    if request.method in ('POST', 'PUT', 'PATCH', 'DELETE'):
        if not SCANNER_TOKEN or request.headers.get('X-Scanner-Token') != SCANNER_TOKEN:
            return jsonify({'success': False, 'error': 'Scanner pairing token required'}), 401

@app.after_request
def cors_headers(response):
    origin = request.headers.get('Origin')
    if origin in ALLOWED_ORIGINS:
        response.headers['Access-Control-Allow-Origin'] = origin
        response.headers['Vary'] = 'Origin'
        response.headers['Access-Control-Allow-Headers'] = 'Content-Type, X-Scanner-Token'
        response.headers['Access-Control-Allow-Methods'] = 'GET, POST, OPTIONS'
    response.headers['X-Content-Type-Options'] = 'nosniff'
    return response

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
        if not scanner_device and not SIMULATION_ENABLED:
            raise Exception("Scanner hardware is not available")

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

        if scanner or (not SANE_AVAILABLE and SIMULATION_ENABLED):
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
                'message': 'DS-530 II scanner not detected or simulation is disabled'
            }), 404

    except Exception as e:
        app.logger.exception('Scanner status check failed')
        return jsonify({
            'available': False,
            'error': 'Scanner status is temporarily unavailable'
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
        if quality not in ('draft', 'normal', 'high') or color_mode not in ('color', 'Gray', 'Lineart'):
            return jsonify({'success': False, 'error': 'Invalid scan options'}), 400
        try: resolution = int(resolution)
        except (TypeError, ValueError): return jsonify({'success': False, 'error': 'Invalid resolution'}), 400
        if resolution not in (150, 200, 300, 400, 600):
            return jsonify({'success': False, 'error': 'Unsupported resolution'}), 400

        # Get scanner
        scanner = get_scanner_device()

        if not scan_lock.acquire(blocking=False):
            return jsonify({'success': False, 'error': 'A scan is already in progress'}), 429
        try:
            pdf_buffer = scan_to_pdf(scanner, quality, color_mode, resolution)
        finally:
            scan_lock.release()

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
        app.logger.exception('Scanner request failed')
        return jsonify({
            'success': False,
            'error': 'Scanning failed. Please try again.'
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

    app.run(host='127.0.0.1', port=18622, debug=False)
