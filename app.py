# app.py
from flask import Flask, request, jsonify
from predict_survey import predict  # Ensure this file is in the same directory
import os

app = Flask(__name__)

# ---------------- Root route ----------------
@app.route("/", methods=["GET"])
def index():
    return "VocaAI Prediction API is live."

# ---------------- Predict API ----------------
@app.route("/predict", methods=["POST"])
def predict_api():
    data = request.get_json()
    if not data:
        return jsonify({"error": "No JSON provided"}), 400

    try:
        result = predict(data)
        return jsonify(result)
    except Exception as e:
        return jsonify({"error": str(e)}), 500

# ---------------- Run app ----------------
if __name__ == "__main__":
    # Render sets the PORT environment variable automatically
    port = int(os.environ.get("PORT", 5000))
    app.run(host="0.0.0.0", port=port)
