#!/usr/bin/env python3
import json
import re
import sys

import requests


def pretty_print(obj):
    print(json.dumps(obj, indent=2, ensure_ascii=False))


def parse_page_data(line):
    pattern = r'pageData = ({.*});'
    matched = re.search(pattern, line)
    if matched:
        return json.loads(matched.group(1))
    return None


def parse_page_props(line):
    pattern = r'pageProps = ({.*});'
    matched = re.search(pattern, line)
    if matched:
        return json.loads(matched.group(1))
    return None


def get_dailymotion(data):
    sources = None
    for item in data['props']:
        if item['name'] == 'model':
            sources = item['value']['videoSources']
            break

    if sources is None:
        return None

    dailymotion = None
    for src in sources:
        if src['name'] == 'dailymotion':
            dailymotion = src
            break

    if dailymotion is None:
        return None

    urls = []
    for video in dailymotion['videos']:
        video_id = video['id']
        url = 'https://www.dailymotion.com/video/{}'.format(video_id)
        urls.append(url)

    return urls


def main(url):
    resp = requests.get(url)
    html = resp.text

    page_data = None
    page_props = None
    for line in html.split('\n'):
        if line.find('var pageData') != -1:
            page_data = parse_page_data(line)
            continue
        if line.find('var pageData') != -1:
            page_props = parse_page_props(line)
            continue

    dailymotion = get_dailymotion(page_data)
    print(dailymotion)

    #print(json.dumps(page_data, indent=2))
    #print(json.dumps(page_props, indent=2))


def usage():
    print('Usage: {} [url]'.format(sys.argv[0]))


if __name__ == '__main__':
    if len(sys.argv) < 2:
        usage()
        sys.exit(-1)

    url = sys.argv[1]
    main(url)
