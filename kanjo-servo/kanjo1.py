import cv2
from deepface import DeepFace
from PIL import Image, ImageDraw, ImageFont
import numpy as np
import requests
import time

emotion_map = {
    'angry': '怒り',
    'neutral': '普通',
    'happy': '楽しい',
    'sad': '悲しい',
    'fear': '疲れた'
}

emotion_thresholds = {
    '怒り': 0.25,
    '普通': 0.3,
    '楽しい': 0.3,
    '悲しい': 0.3,
    '疲れた': 0.3
}

font_path = r"/home/rinta/ダウンロード/ki_kokugo/font_1_kokugl_1.15_rls.ttf"
font = ImageFont.truetype(font_path, 32)

cap = cv2.VideoCapture(0)

last_request_time = time.time()

while cap.isOpened():
    ret, frame = cap.read()
    if not ret:
        break

    try:
        result = DeepFace.analyze(frame, actions=['emotion'], enforce_detection=False)
        emotions = result[0]['emotion']
        mapped_emotions = {}
        for key, value in emotion_map.items():
            score = emotions.get(key, 0)
            mapped_emotions[value] = score

        frame_pil = Image.fromarray(cv2.cvtColor(frame, cv2.COLOR_BGR2RGB))
        draw = ImageDraw.Draw(frame_pil)

        for emotion, score in mapped_emotions.items():
            if score >= emotion_thresholds[emotion]:
                text = f"{emotion}: {score:.2f}"
                y_position = 50 + 40 * list(mapped_emotions.keys()).index(emotion)
                draw.text((50, y_position), text, font=font, fill=(0, 255, 0, 255))

        current_time = time.time()
        if current_time - last_request_time >= 10:
            try:
                filtered_emotions = {emotion: score for emotion, score in mapped_emotions.items() if score > 0.6}
                if filtered_emotions:
                    highest_emotion = max(filtered_emotions, key=filtered_emotions.get)
                    base_ip = "http://192.168.200.77"
                    emotion_links = {
                        '怒り': f"{base_ip}/0",
                        '楽しい': f"{base_ip}/4",
                        '悲しい': f"{base_ip}/2",
                        '疲れた': f"{base_ip}/1",
                        '普通': f"{base_ip}/3"
                    }
                    if highest_emotion in emotion_links:
                        response = requests.get(emotion_links[highest_emotion], timeout=5)
                        print(f"Sent request for '{highest_emotion}': {response.status_code}")
            except requests.exceptions.RequestException as e:
                print(f"Request failed: {e}")
            finally:
                last_request_time = current_time

        cv2.waitKey(500)
        frame = cv2.cvtColor(np.array(frame_pil), cv2.COLOR_RGB2BGR)

    except Exception as e:
        print(f"Error: {e}")

    cv2.imshow('Emotion Recognition', frame)

    if cv2.waitKey(1) & 0xFF == ord('q'):
        break

cap.release()
cv2.destroyAllWindows()
