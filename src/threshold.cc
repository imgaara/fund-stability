#include <math.h>
#include <algorithm>
#include <complex>
#include <vector>
using namespace std;

long long GetEnvironmentInteger(const char* name, long long default_value) {
  const char* value = getenv(name);
  long long result;
  if (value != nullptr && sscanf(value, "%lld", &result) > 0) {
    return result;
  }
  return default_value;
}

void FFT(vector<complex<double>>* values, bool inverse) {
  double theta = (inverse ? -2 : 2) * M_PI / values->size();
  for (int m = values->size(); m >= 2; m >>= 1) {
    int mh = m >> 1;
    for (int i = 0; i < mh; i++) {
      complex<double> w = exp(complex<double>(0, i * theta));
      for (int j = i; j < values->size(); j += m) {
        int k = j + mh;
        complex<double> x = (*values)[j] - (*values)[k];
        (*values)[j] += (*values)[k];
        (*values)[k] = w * x;
      }
    }
    theta *= 2;
  }
  int i = 0;
  for (int j = 1; j < values->size() - 1; j++) {
    for (int k = values->size() >> 1; k > (i ^= k); k >>= 1);
    if (j < i) {
      swap((*values)[i], (*values)[j]);
    }
  }
  if (inverse) {
    for (complex<double>& value : *values) {
      value /= values->size();
    }
  }
}

int main(int argc, char** argv) {
  int power;
  scanf("%d", &power);

  vector<int> values;
  int minimum = 0x7fffffff, maximum = 0x80000000;
  for (int value; scanf("%d", &value) > 0;) {
    values.push_back(value);
    minimum = min(minimum, value);
    maximum = max(maximum, value);
  }

  int size = (maximum - minimum) * power + 1;
  while ((size & (size - 1)) != 0) {
    size = (size | (size - 1)) + 1;
  }
  vector<complex<double>> probabilities(size, complex<double>(0.0, 0.0));
  for (int value : values) {
    probabilities[value - minimum] += 1.0 / values.size();
  }

  FFT(&probabilities, false);

  for (complex<double>& probability : probabilities) {
    probability = pow(probability, power);
  }

  FFT(&probabilities, true);

  const int base = minimum * power;

  if (GetEnvironmentInteger("SHOW_PROBABILITIES", 0)) {
    for (int i = 0; i < probabilities.size(); i++) {
      printf("%d\t%.8f\n", base + i, probabilities[i].real());
    }
  } else {
    double sum = 0;
    for (int i = 0; i < probabilities.size(); i++) {
      if (base + i > 0) break;
      sum += probabilities[i].real();
    }
    printf("%.8f\n", 1 - sum);
  }

  return 0;
}
