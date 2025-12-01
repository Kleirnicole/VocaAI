from flask import Flask, request, jsonify
from predict_survey import predict
import os

app = Flask(__name__)

@app.route("/predict", methods=["POST"])
def predict_api():
    data = request.get_json()
    if not data:
        return jsonify({"error": "No input JSON provided"}), 400
    try:
        result = predict(data)
        return jsonify(result)
    except Exception as e:
        return jsonify({"error": str(e)}), 500


if __name__ == "__main__":
    port = int(os.environ.get("PORT", 5000))  # Render PORT
    app.run(host="0.0.0.0", port=port)
