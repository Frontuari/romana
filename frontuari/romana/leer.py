import serial
ser = serial.Serial()
ser.baudrate = 9600
ser.port = 'COM1'
ser.open()
 



archivo='datoBascula.txt'
f = open(archivo,'a')

while 1 :
    f.write(ser.readline().decode('utf-8'))
    f.close()
    f = open(archivo,'a')