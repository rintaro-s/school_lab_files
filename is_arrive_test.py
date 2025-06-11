import requests
import asyncio

async def check_and_notify():
    url = "https://portfolio.toba-cmt.ac.jp/Portfolio?handler=ExportReportCard"
    webhook_url = "https://discord.com/api/webhooks/1382235570956140644/xTbCgoEhbhRzwGiFkrhQ6xn4Gp6wJaTFXBqGAXetI0iDVC2sqfUV7M28Hi_RrBgT4hiC"
    
    # Cookie設定
    cookies = {
        ".AspNetCore.Cookies": "CfDJ8E-lgCEFywpFh32dKZn5sKERxezc2LZSIhx5QvxTIzSGOIZ6D-iX9fR1Vr2R5R84DqUK2rd4Yt4TETLwOapUJh7JNeMVYpWOQwY3Z7ijVf29fhIM1vI2G-3b771Xj3hb0CuXB2eYLQNgezR9c4OVEO0BkmKxJuIQQfz9vDRom6EoLG57h37Ayp0eYHVe9QwPOvC2o7pK-CmSo-DX0Yu81-VbwCbi-vHr4Rr1a7zyVaGpkeiuY9JVDxDPG9b6Va_0MHXE72FwboLy9JvZLqElyTVSoH5-n2WVAESW0l_ZnlTIVahcOCvSNyFWlQ7pz7ecvxOfbdsqncozGChvm-FFXteqM8f4gJLluKwQhIot2HqEqNKWGOPiIeYluX8kAUqNKBCbIkNl4G0WTpYQiMqO2kZiqAUVwLOgerkt9kSnsWO-78rz88pP8oFosC2juW1zYnbjK6lIPAHN88t3b3yZe1iwPXIpqJL_yO30Uk8UfPNkm43w78BdxCG_VVv1PuGGfBchwT1jRulkHiJt-tXREHmgfnLNcHj23byJeqH_IbWrnXJX9WJo3JRW58QZd9QkTzoSYcABuK31FHAdEltrU322QYczGTlvMIZrVgs2d6Q0pUUpEFPwrghhYBUOLPaRISxuaDlkEOdK46ylQ9wwnJLV7o0rkwe7uKVLPEYAzIWmu48cyYrB02zRYpPnNx-I8VjG4Z6GrtPNr9m8GwPpaWCsTagu3M9Ka2EqH990uj9qTdedpkNtOmDMlLA38HxSg3VRvPBoP3v6Fr9XLiv7aFgwiUsdZgSQwcNx8WSDWv8MJy7FHoM9gv88dltOIOWqcIS1pZ_--6VNEuQPfZ2RFN9tr9jDvGuN5nYILr5ZFFQ4tDDW-th0xvvoEpUtpCMn08Tmu8dqmQUT9Cg2Q4A2RUvp1CBBKbtPNwSaxOfEme92ZDUxRnbTsdknPoJB7C2t_W6U0OQgUwLJApZUYZ9qDgN2xfUzYkSj3QDlGYVOX6hssHTBrmLUSOIY4xcANG9xKorLnpmS3pAqb46k6RGo95Z_p4cNCkYNVDokso8lftm6UYWP0wZJFma2jwtiGGw1tw6cqI90kwWrMgg93DvfPS2giu0ziCD9O0LbAacXBb-AOaOlEtN_HYZFx1Ve5ffFd4z5eBk-_FOEwlxVBg_Ca4Nx7WOVKbbLakUscqoIXsKXp2GlpehLemGbmYW4R0WbqNjxYADUWPnki2qFn3V74iAOTD9KaSLR7Kw2dWdShRKJiU3hOWzr8CxwzgW1B8ELX_B6nPo6BcrGGbAlsWFnQklHxlknX0EkAgQfy6fdrE5Z0kgTGas0YI_RqBTRQw0vOjbaqPDF4uFZrnH_SRKjvFH4a9bv9FNfwMg4SYKTsN-VjeiuLCZ5p5o3YEPjzjQqHC9iCyFxSZJDcCyTzOadh_hjaA-ELtkMUcTVRzxaCKdQzrdzkNgiXb9kt23HKuCPK4Vi88JYuzJMi41T3deB_R_zPjafKKOJwF39DEChQ3FVaDi-SuJDLeSHuECJKkmOdCWlgPI8SqJzQJ2qAHYKBa0fcG8lRcsw-oGUTy_hUEQ2hbJsemTbVOlAd_NGjQJSVZ3H831BTnCH3oO1U8tbw2vpxhOgk5SmKcgMI_O_ULIF5caizjUOrvGtIk5l1duQhqWKbFpE--QZg6k-HbFhaO0d8YgpVa3XJBgUlGIB_ngfJATdI07qJjhSLRPDm9aSnRNFrorjzyd_xFCi6oCsJ2k2bRIpuGzN3LdmSBxN_w8Ig8SKPGNJ4LdPKOdOqeYGXWb-Xr0sFqoJzO2TxGlvtCIrMxFU2nAtPfbK7xU3REE_hpXECYNgN9kVMNWgkWh66lqWbI-JeWms9U_YnrF_mQuv80W4wwO0JQKXGR4gAp-x9m6Vm8LfYoQnuuHAlwApcCG2x1gszMdLKFqvK5nf7-VQwkKp_NvWRd9TsVoV4-FAo8jIDFqixp5PDPDdSzKdlt4l-SqfrV8eHliIJ653yPdlxwqaAgUtWd11v3gULiqziVNdkzzdSLIYT5nglR8R4RI11AJfF7POEjNdgjjM07OCkZpUc_EBeeYxTsC_9uNBUu0t-Of8BZGO3toIimwa6a4nfi8LKCtNMps7D8NQqg3D_ioMPc0LSBhpOJIrWITnrl33CgfjbQB4eN2kj6vFzG1EEdrEHY2pGhDb44VAYS6hLrgjdT7SN_CoZlyaqzdSpuzzvlnjVYCfwzabqmFrQJ0Gk8uaO_ZMVzcoZgSdDuFBCmlzDEICTxGYv9xIc5BsgB27UKtttferxL8AoCX31kiypLwYHrnicnK2oHpWvMpMmeQH5z18d2q_9VRq-pxtuk5GzQlJ5R8u-2hzvRZXlPHDZdQDm3ubmI0HaG9i_C-Tt1CHZLd_1c6v2RybQrEeC1sCEdVxUdsPatiqpJcq9ClH8vnh4dhFeG1DaU8CBwArJAInIU6wzlZZTdv9SV78_0bgMr7VW-u8Q7l09PxKan2G4XYAekEfk-KD-8IMZia-PkfY5uhGQ1hQBuNv9XREq02VU581fNQxzcy40mwYlPd2BB5f7hRZov3cMvrDT-6mqGNt_NOHMg0w4tA21-hlaCVAVaOD5ofhpBJFZkBceJsfWakhXsZ09M3GhNo7hCbKM7XwzqRh0JsZT3FMQaYqT_yeXmBXQIcJZU8M2LPg4uTZAadVO-rMMC7p225IBzOSapMdO8JhcF7LrlokWm-AhRXcorpLsWwoPgL-21na2vShbAhR22At97hZ_yM5O4KkLzbidXGGVDmDzyC12gTekF-rxJaWHXVIk4Zx6DFOdC9eEKSfIuyjuAK0w9Tei8gZ2ne8WOqLP7_nWU6YM0gfLdmjIR6g-UxHpHt66zaO4r7Bp0ZZMkpBYSrtlTtnILwzVsjP3yhOWJm_s2kpZvZj1VW6BHnbAR3-iykHLMrsew"
    }
    
    while True:
        try:
            # ファイルをダウンロード（Cookieを追加）
            response = requests.get(url, cookies=cookies)
            response.raise_for_status()
            
            # ファイルサイズをチェック（80MB = 80 * 1024 * 1024 bytes）
            file_size = len(response.content)
            size_mb = file_size / (1024 * 1024)
            
            if file_size >= 0.1 * 1024 * 1024:
                # Webhookでメッセージを送信
                data = {
                    "content": f"ファイルサイズが80KB以上です。成績が公開された可能性があります: {size_mb:.2f}MB"
                }
                
                webhook_response = requests.post(webhook_url, json=data)
                webhook_response.raise_for_status()
                print(f"Discord通知を送信しました: {size_mb:.2f}MB")
                break  # 通知を送信したら動作を停止
            else:
                print(f"ファイルサイズ: {size_mb:.2f}MB (0.1MB未満)")
                
        except Exception as e:
            print(f"エラーが発生しました: {e}")
        
        # 5分間隔でチェック
        await asyncio.sleep(300)

if __name__ == "__main__":
    asyncio.run(check_and_notify())
