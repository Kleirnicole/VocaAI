import os
from flask import Flask
from predict_survey import predict

app = Flask(__name__)

@app.route("/predict", methods=["POST"])
def predict_api():
    from flask import request, jsonify
    data = request.get_json()
    if not data:
        return jsonify({"error": "No JSON provided"}), 400
    try:
        return jsonify(predict(data))
    except Exception as e:
        return jsonify({"error": str(e)}), 500

if __name__ == "__main__":
    port = int(os.environ.get("PORT", 10000))  # default to 10000 for local testing
    app.run(host="0.0.0.0", port=port)
